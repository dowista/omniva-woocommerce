<?php

/** Synchronizes an account's event stream in bounded, replayable pages. */
class OmnivaLt_Tracking_Sync
{
  const HOOK = 'omnivalt_tracking_sync';
  const GROUP = 'omnivalt_tracking';
  const LINKS_HOOK = 'omnivalt_tracking_fill_links';
  const RETRY_ORDER_HOOK = 'omnivalt_tracking_retry_order_links';
  private const LINKS_OPTION = 'omnivalt_tracking_links_v1';
  private const LINKS_BATCH_SIZE = 100;
  private const ORDER_RETRY_DELAYS = array(1, 5, 30, 60);
  private const SCHEDULE_CHECK_TRANSIENT = 'omnivalt_tracking_schedule_check';

  public static function account()
  {
    $settings = OmnivaLt_Core::get_settings();
    if ( ! is_array($settings) || empty($settings['api_user']) || empty($settings['api_pass'])
      || ! is_string($settings['api_user']) || ! is_string($settings['api_pass']) ) {
      return false;
    }
    $user = trim($settings['api_user']);
    if ( '' === $user ) {
      return false;
    }
    $environment = defined('_OMNIVA_API_USE_TEST_OMX_') && _OMNIVA_API_USE_TEST_OMX_ ? 'test' : 'live';
    return array('id' => hash('sha256', $environment . "\0" . $user), 'user' => $user, 'password' => $settings['api_pass']);
  }

  public static function initialize_schedule()
  {
    if ( OMNIVALT_VERSION === get_transient(self::SCHEDULE_CHECK_TRANSIENT) ) {
      return;
    }
    self::ensure_scheduled();
  }

  public static function ensure_scheduled()
  {
    if ( ! function_exists('as_get_scheduled_actions') || ! function_exists('as_schedule_single_action') ) {
      return;
    }
    set_transient(self::SCHEDULE_CHECK_TRANSIENT, OMNIVALT_VERSION, 5 * MINUTE_IN_SECONDS);
    if ( ! self::account() ) {
      return;
    }
    $hook = self::links_ready() ? self::HOOK : self::LINKS_HOOK;
    try {
      if ( empty(self::pending_actions($hook)) ) {
        as_schedule_single_action(time() + (self::HOOK === $hook ? MINUTE_IN_SECONDS : 0), $hook, array(), self::GROUP, true);
      }
    } catch ( \Throwable $e ) {
      // The next queue check can retry without interrupting the current request.
    }
  }

  public static function links_ready()
  {
    $progress = get_option(self::LINKS_OPTION, array());
    return true === ($progress['complete'] ?? false);
  }

  public static function fill_links()
  {
    global $wpdb;
    if ( self::links_ready() ) {
      self::ensure_scheduled();
      return;
    }
    $lock = 'omnivalt-links-' . substr(hash('sha256', $wpdb->prefix), 0, 32);
    try {
      self::storage()->with_lock($lock, 0, function() {
        if ( ! self::links_ready() ) {
          self::fill_links_batch();
        }
      });
    } finally {
      // The locked callback can finish the backfill and update its stored marker.
      // @phpstan-ignore if.alwaysFalse
      if ( self::links_ready() ) {
        self::schedule(time());
      } else {
        as_schedule_single_action(time() + MINUTE_IN_SECONDS, self::LINKS_HOOK, array(), self::GROUP);
      }
    }
  }

  private static function fill_links_batch()
  {
    global $wpdb;
    $progress = get_option(self::LINKS_OPTION, array());
    if ( ! isset($progress['through'], $progress['from'], $progress['after_order_id'])
      || ! is_int($progress['through']) || ! is_int($progress['from'])
      || ! is_int($progress['after_order_id']) || $progress['after_order_id'] < 0 ) {
      $now = time();
      $progress = array('through' => $now, 'from' => $now - 10 * DAY_IN_SECONDS, 'after_order_id' => 0);
      update_option(self::LINKS_OPTION, $progress, false);
      if ( get_option(self::LINKS_OPTION) !== $progress ) {
        return;
      }
    }
    $storage = self::storage();
    if ( ! $storage->exists() && ! $storage->install() ) {
      return;
    }
    $keys = OmnivaLt_Core::get_configs('meta_keys');
    if ( empty($keys['barcodes']) || ! is_string($keys['barcodes']) ) {
      return;
    }
    // Keep the UTC date range fixed and use IDs for both sorting and pagination.
    $query = array(
      'type' => 'shop_order',
      'status' => array_keys(wc_get_order_statuses()),
      'date_created' => $progress['from'] . '...' . $progress['through'],
      'orderby' => 'ID',
      'order' => 'ASC',
      'limit' => self::LINKS_BATCH_SIZE,
      'return' => 'ids',
    );
    if ( OmnivaLt_Wc::is_using_hpos() ) {
      $query['field_query'] = array(array('field' => 'id', 'value' => $progress['after_order_id'], 'compare' => '>'));
    } else {
      $query['omnivalt_tracking_after_order_id'] = $progress['after_order_id'];
    }
    // WooCommerce returns IDs for return => ids; the stub models the default object query.
    /** @var mixed $order_ids */
    $order_ids = wc_get_orders($query);
    if ( ! is_array($order_ids) || '' !== $wpdb->last_error ) {
      return;
    }
    foreach ( $order_ids as $order_id ) {
      if ( ! is_int($order_id) && (! is_string($order_id) || ! ctype_digit($order_id)) ) {
        return;
      }
      $id = (int) $order_id;
      if ( $id <= $progress['after_order_id'] || ! self::with_order_lock($id, function() use ($storage, $keys, $id, $progress) {
        return self::index_order($storage, $id, $keys['barcodes'], $progress['from'], $progress['through']);
      }) ) {
        return;
      }
      $progress['after_order_id'] = $id;
    }
    if ( count($order_ids) < self::LINKS_BATCH_SIZE ) {
      $progress = array('complete' => true);
    }
    update_option(self::LINKS_OPTION, $progress, false);
  }

  private static function index_order( $storage, $id, $meta_key, $from, $through )
  {
    global $wpdb;
    $order = wc_get_order($id);
    if ( '' !== $wpdb->last_error ) {
      return false;
    }
    if ( ! $order instanceof WC_Order || 'shop_order' !== $order->get_type() ) {
      return true;
    }
    $date = $order->get_date_created();
    if ( ! $date || $date->getTimestamp() < $from || $date->getTimestamp() > $through ) {
      return true;
    }
    $barcodes = self::read_barcode_meta($order, $meta_key);
    if ( false === $barcodes ) {
      return false;
    }
    foreach ( $barcodes as $barcode ) {
      if ( OmnivaLt_Tracking_Storage::valid_barcode($barcode) && ! $storage->link($id, $barcode) ) {
        return false;
      }
    }
    return true;
  }

  public static function fill_links_cpt_where( $where, $query )
  {
    global $wpdb;
    $after = $query->get('omnivalt_tracking_after_order_id');
    if ( ! is_int($after) || $after < 1 || 'shop_order' !== $query->get('post_type') ) {
      return $where;
    }
    $posts_table = OmnivaLt_Tracking_Storage::sql_identifier($wpdb->posts);
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The core posts table name is quoted above.
    return $where . $wpdb->prepare(" AND {$posts_table}.ID > %d", $after);
  }

  public static function manual_retry_after( $state )
  {
    $last_update = max((int) ($state['last_success'] ?? 0), (int) ($state['last_manual'] ?? 0));
    return max(0, $last_update + MINUTE_IN_SECONDS - time());
  }

  public static function run( $manual = false )
  {
    global $wpdb;
    $account = self::account();
    if ( ! $account || ! self::links_ready() ) {
      return false;
    }
    $lock = 'omnivalt-tracking-' . substr(hash('sha256', $wpdb->prefix . $account['id']), 0, 32);
    $acquired = null;
    $completed = self::storage()->with_lock($lock, 0, function() use ($account, $manual) {
      try {
        if ( true === $manual ) {
          $key = 'omnivalt_tracking_sync_' . $account['id'];
          $state = get_option($key, array());
          $state = is_array($state) ? $state : array();
          if ( self::manual_retry_after($state) > 0 ) {
            return true;
          }
          $state['last_manual'] = time();
          if ( ! update_option($key, $state, false) ) {
            throw new \RuntimeException('Manual tracking synchronization could not be recorded.');
          }
        }
        self::run_locked($account);
      } catch ( \Throwable $e ) {
        self::record_failure($account['id']);
        self::schedule(time() + HOUR_IN_SECONDS, false);
      }
      return true;
    }, $acquired);
    if ( ! $completed ) {
      if ( null === $acquired ) {
        self::record_failure($account['id'], 'storage');
      }
      self::schedule(time() + 60, false);
    }
    return $completed;
  }

  public static function deactivate()
  {
    delete_transient(self::SCHEDULE_CHECK_TRANSIENT);
    if ( function_exists('as_unschedule_all_actions') ) {
      as_unschedule_all_actions('', array(), self::GROUP);
    }
    wp_unschedule_hook(self::RETRY_ORDER_HOOK);
  }

  private static function run_locked( $account )
  {
    global $wpdb;
    $storage = new OmnivaLt_Tracking_Storage($wpdb);
    if ( ! $storage->exists() && ! $storage->install() ) {
      self::record_failure($account['id'], 'storage');
      self::schedule(time() + HOUR_IN_SECONDS, false);
      return;
    }
    $key = 'omnivalt_tracking_sync_' . $account['id'];
    $barcodes = $storage->linked_pending_barcodes($account['id']);
    if ( false === $barcodes ) {
      throw new \RuntimeException('Tracking storage unavailable.');
    }
    foreach ( $barcodes as $barcode ) {
      $events = $storage->pending($account['id'], $barcode);
      if ( false === $events ) {
        throw new \RuntimeException('Tracking pending events could not be read.');
      }
      if ( empty($events) ) {
        continue;
      }
      $merged = self::merge_stored_history($storage, $account['id'], $barcode, $events);
      if ( false === $merged ) {
        throw new \RuntimeException('Tracking state could not be saved.');
      }
      if ( true === $merged ) {
        $last = end($events);
        if ( ! $storage->delete_pending_through($account['id'], $barcode, $last['eventId']) ) {
          throw new \RuntimeException('Tracking pending events could not be saved.');
        }
      }
    }
    $sync = get_option($key, array());
    $sync = is_array($sync) ? $sync : array();
    if ( ! empty($sync['last_success']) && $sync['last_success'] < time() - 30 * DAY_IN_SECONDS && ($sync['gap_seen'] ?? 0) !== $sync['last_success'] ) {
      $sync['gap'] = true;
      $sync['gap_seen'] = $sync['last_success'];
      $sync['gap_id'] = hash('sha256', 'last_success:' . $sync['last_success']);
      // Persist before querying: an API failure must not hide the data gap.
      update_option($key, $sync, false);
    }
    if ( empty($sync['start']) || (empty($sync['cursor']) && strtotime($sync['start'] . ' UTC') < time() - 29 * DAY_IN_SECONDS) ) {
      if ( ! empty($sync['start']) ) {
        $sync['gap'] = true;
        $sync['gap_id'] = hash('sha256', 'start:' . $sync['start']);
      }
      $sync['start'] = gmdate('Y-m-d\TH:i:s', time() - 10 * DAY_IN_SECONDS);
      update_option($key, $sync, false);
      if ( get_option($key) !== $sync ) {
        self::record_failure($account['id'], 'storage');
        self::schedule(time() + HOUR_IN_SECONDS, false);
        return;
      }
    }
    $api = new OmnivaLt_Api_Omx_Tracking($account['user'], $account['password']);
    $result = ! empty($sync['cursor']) ? $api->get_events_since_id($sync['cursor']) : $api->get_events_since_time($sync['start']);
    if ( empty($result['ok']) ) {
      self::record_failure($account['id'], $result['error'] ?? 'unknown', $result['http_status'] ?? 0);
      self::schedule(time() + HOUR_IN_SECONDS, false);
      return;
    }
    $revisions = array();
    $load = function( $barcode ) use ($storage, $account, &$revisions) {
      $linked = $storage->has_link($barcode);
      if ( true !== $linked ) {
        return $linked;
      }
      $row = $storage->read($account['id'], $barcode);
      if ( false === $row ) {
        return false;
      }
      $revisions[$barcode] = null === $row ? null : $row['revision'];
      $state = null === $row ? array() : $row['state'];
      $events = $storage->pending($account['id'], $barcode);
      if ( false === $events ) {
        return false;
      }
      return self::state_with_events($state, $events);
    };
    $write_conflict = false;
    $save = function( $barcode, $state ) use ($storage, $account, &$revisions, &$write_conflict) {
      $result = self::save_page_state($storage, $account['id'], $barcode, $state, $revisions[$barcode]);
      $write_conflict = null === $result;
      return true === $result;
    };
    $advance = function( $cursor ) use ($key, &$sync) {
      $sync['cursor'] = $cursor;
      update_option($key, $sync, false);
      return get_option($key) === $sync;
    };
    $pending = function( $event ) use ($storage, $account) { return $storage->save_pending($account['id'], $event); };
    if ( ! OmnivaLt_Tracking_Sync::process_page($result['data']['content'], $load, $save, $advance, $pending) ) {
      self::record_failure($account['id'], $write_conflict ? 'write_conflict' : 'storage');
      self::schedule(time() + ($write_conflict ? MINUTE_IN_SECONDS : HOUR_IN_SECONDS), $write_conflict);
      return;
    }
    $last_page = $result['data']['last'];
    $sync['in_progress'] = ! $last_page;
    unset($sync['failed_since'], $sync['last_failure']);
    if ( $last_page ) {
      // No ID exists for an empty feed; retain a rolling overlap for bootstrap.
      if ( empty($sync['cursor']) ) {
        $sync['start'] = gmdate('Y-m-d\TH:i:s', time() - 10 * DAY_IN_SECONDS);
      }
      $sync['last_success'] = time();
    }
    update_option($key, $sync, false);
    if ( get_option($key) !== $sync ) {
      self::record_failure($account['id'], 'storage');
      self::schedule(time() + HOUR_IN_SECONDS, false);
      return;
    }
    if ( $last_page ) {
      $storage->purge_pending(time() - 7 * DAY_IN_SECONDS);
      self::schedule(time() + HOUR_IN_SECONDS);
    } else {
      self::schedule(time() + MINUTE_IN_SECONDS);
    }
  }

  private static function record_failure( $account_id, $code = 'unknown', $http_status = 0 )
  {
    $key = 'omnivalt_tracking_sync_' . $account_id;
    $sync = get_option($key, array());
    $sync = is_array($sync) ? $sync : array();
    if ( empty($sync['failed_since']) ) {
      $sync['failed_since'] = time();
    }
    $supported_codes = array('transport', 'http', 'invalid_response', 'invalid_cursor_response', 'unordered_events',
      'invalid_cursor', 'invalid_timestamp', 'missing_credentials', 'storage', 'write_conflict');
    $code = in_array($code, $supported_codes, true) ? $code : 'unknown';
    $sync['last_failure'] = array('code' => $code);
    if ( is_string($http_status) && 1 === preg_match('/^[1-5][0-9]{2}$/D', $http_status) ) {
      $http_status = (int) $http_status;
    }
    if ( 'http' === $code && is_int($http_status) && $http_status >= 100 && $http_status <= 599 ) {
      $sync['last_failure']['http_status'] = $http_status;
    }
    update_option($key, $sync, false);
  }

  public static function schedule( $timestamp, $replace = true )
  {
    if ( ! function_exists('as_schedule_single_action') ) {
      return;
    }
    try {
      $pending = self::pending_actions(self::HOOK, $replace);
      if ( ! $replace && ! empty($pending) ) {
        return;
      }
      $action_id = (int) as_schedule_single_action($timestamp, self::HOOK, array(), self::GROUP, false);
      // Older scheduler versions may expose the scheduling function without a callable store.
      // @phpstan-ignore booleanNot.alwaysFalse
      if ( $action_id < 1 || empty($pending) || ! is_callable(array('ActionScheduler', 'store')) ) {
        return;
      }
      // Cancel only actions observed before insertion, leaving concurrent enqueues intact.
      $store = ActionScheduler::store();
      foreach ( $pending as $old_id ) {
        if ( (int) $old_id !== $action_id && 'pending' === $store->get_status($old_id) && ! $store->get_claim_id($old_id) ) {
          $store->cancel_action($old_id);
        }
      }
    } catch ( \Throwable $e ) {
      // Keep existing actions available and let the queue runner recover missing work.
    }
  }

  private static function pending_actions( $hook, $replace = false )
  {
    if ( ! function_exists('as_get_scheduled_actions') ) {
      return array();
    }
    $query = array('hook' => $hook, 'args' => array(), 'group' => self::GROUP, 'status' => 'pending', 'per_page' => $replace ? -1 : 1);
    if ( $replace ) {
      $query['claimed'] = false;
    }
    return as_get_scheduled_actions($query, 'ids');
  }

  public static function storage()
  {
    global $wpdb;
    return new OmnivaLt_Tracking_Storage($wpdb);
  }

  public static function registered( $order_id, $barcodes )
  {
    $account = self::account();
    $order_id = (int) $order_id;
    if ( ! $account || $order_id < 1 || ! is_array($barcodes) || empty($barcodes) ) {
      return;
    }
    $registration_dates = array_fill_keys(
      array_filter(array_map(array('OmnivaLt_Tracking_Storage', 'normalize_barcode'), $barcodes), 'is_string'),
      gmdate('Y-m-d\TH:i:s\Z')
    );
    try {
      if ( self::with_order_lock($order_id, function() use ($account, $order_id, $registration_dates) {
        return self::restore_order_links($account, $order_id, $registration_dates);
      }) ) {
        return;
      }
    } catch ( \Throwable $e ) {
      // The order metadata is already saved. Retry its index operation separately.
    }
    if ( ! self::schedule_order_retry($order_id, 0) ) {
      throw new \RuntimeException('Tracking order recovery could not be scheduled.');
    }
  }

  public static function retry_order_links( $order_id, $attempt, $deleted = false, $wp_cron = false )
  {
    if ( ! is_int($order_id) || $order_id < 1 || ! is_int($attempt) || ! isset(self::ORDER_RETRY_DELAYS[$attempt]) ) {
      return;
    }
    try {
      if ( self::with_order_lock($order_id, function() use ($order_id, $deleted) {
        return true === $deleted ? self::remove_order_links($order_id) : self::restore_order_links(self::account(), $order_id);
      }) ) {
        return;
      }
    } catch ( \Throwable $e ) {
      // Queue retries without recording exception messages that can contain database details.
    }
    // An exception from native WP-Cron would also interrupt unrelated cron events.
    if ( ! self::schedule_order_retry($order_id, $attempt + 1, $deleted) && true !== $wp_cron ) {
      throw new \RuntimeException('Tracking order links could not be restored.');
    }
  }

  private static function restore_order_links( $account, $order_id, $registration_dates = array() )
  {
    global $wpdb;
    $order = wc_get_order($order_id);
    if ( '' !== $wpdb->last_error ) {
      return false;
    }
    if ( ! $account || ! $order instanceof WC_Order || 'shop_order' !== $order->get_type() ) {
      return true;
    }
    $keys = OmnivaLt_Core::get_configs('meta_keys');
    $barcodes = self::read_barcode_meta($order, $keys['barcodes']);
    if ( false === $barcodes ) {
      return false;
    }
    if ( empty($barcodes) ) {
      return true;
    }
    $storage = self::storage();
    if ( ! $storage->exists() && ! $storage->install() ) {
      return false;
    }
    $previous = $storage->order_barcodes($order_id);
    // Reconcile every indexed barcode against the authoritative metadata under the lock.
    return false !== $previous && self::register_links($storage, $account['id'], $order_id, $barcodes, $previous, $registration_dates);
  }

  private static function read_barcode_meta( $order, $meta_key )
  {
    global $wpdb;
    // Force a fresh metadata read after taking the order lock.
    $order->read_meta_data(true);
    $barcodes = $order->get_meta($meta_key, true, 'edit');
    if ( '' !== $wpdb->last_error ) {
      return false;
    }
    return is_array($barcodes) || OmnivaLt_Tracking_Storage::valid_barcode($barcodes) ? (array) $barcodes : array();
  }

  private static function with_order_lock( $order_id, $operation )
  {
    global $wpdb;
    $lock = 'omnivalt-order-links-' . substr(hash('sha256', $wpdb->prefix . ':' . $order_id), 0, 32);
    return self::storage()->with_lock($lock, 1, $operation);
  }

  private static function schedule_order_retry( $order_id, $attempt, $deleted = false )
  {
    if ( ! isset(self::ORDER_RETRY_DELAYS[$attempt]) ) {
      return false;
    }
    $args = true === $deleted ? array($order_id, $attempt, true) : array($order_id, $attempt);
    $cron_args = array($order_id, $attempt, true === $deleted, true);
    $when = time() + self::ORDER_RETRY_DELAYS[$attempt] * MINUTE_IN_SECONDS;
    if ( wp_next_scheduled(self::RETRY_ORDER_HOOK, $cron_args) ) {
      return true;
    }
    try {
      // Older Action Scheduler versions enforce uniqueness by hook and group, across all orders.
      if ( (function_exists('as_next_scheduled_action') && as_next_scheduled_action(self::RETRY_ORDER_HOOK, $args, self::GROUP))
        || (function_exists('as_schedule_single_action') && as_schedule_single_action($when, self::RETRY_ORDER_HOOK, $args, self::GROUP)) ) {
        return true;
      }
    } catch ( \Throwable $e ) {
      // WordPress persists a fallback event separately from Action Scheduler.
    }
    if ( wp_schedule_single_event($when, self::RETRY_ORDER_HOOK, $cron_args) ) {
      return true;
    }
    // A concurrent enqueue can win after the initial check.
    return (bool) wp_next_scheduled(self::RETRY_ORDER_HOOK, $cron_args);
  }

  private static function register_links( $storage, $account, $order_id, $barcodes, $previous_barcodes, $registration_dates )
  {
    $all_linked = true;
    foreach ( $barcodes as $barcode ) {
      $barcode = OmnivaLt_Tracking_Storage::normalize_barcode($barcode);
      if ( false === $barcode || ! $storage->link($order_id, $barcode) ) {
        $all_linked = false;
        continue;
      }
      $row = $storage->read($account, $barcode);
      if ( null === $row ) {
        $state = array('registered_locally' => true, 'shipment_status' => 'registered', 'events' => array());
        if ( isset($registration_dates[$barcode]) ) {
          $state['registered_at'] = $registration_dates[$barcode];
        }
        $storage->write_result($account, $barcode, $state, null);
      }
      $pending = $storage->pending($account, $barcode);
      if ( is_array($pending) && ! empty($pending) ) {
        self::merge_stored_history($storage, $account, $barcode, $pending);
      }
    }
    return $all_linked && (empty($previous_barcodes) || $storage->unlink_replaced_barcodes($order_id, $previous_barcodes, $barcodes));
  }

  public static function delete_order( $order_id )
  {
    $order_id = (int) $order_id;
    if ( $order_id < 1 ) {
      return;
    }
    try {
      if ( self::with_order_lock($order_id, function() use ($order_id) {
        return self::remove_order_links($order_id);
      }) ) {
        return;
      }
    } catch ( \Throwable $e ) {
      // The deletion hook runs after the order is gone.
    }
    self::schedule_order_retry($order_id, 0, true);
  }

  private static function remove_order_links( $order_id )
  {
    global $wpdb;
    $storage = self::storage();
    return $storage->exists() ? $storage->delete_order($order_id) : '' === $wpdb->last_error;
  }

  public static function delete_legacy_order( $order_id, $post )
  {
    if ( ! $post instanceof WP_Post || 'shop_order' !== $post->post_type || OmnivaLt_Wc::is_using_hpos() ) {
      return;
    }
    self::delete_order($order_id);
  }

  private static function merge_stored_history( $storage, $account, $barcode, $events )
  {
    for ( $attempt = 0; $attempt < 3; $attempt++ ) {
      $row = $storage->read($account, $barcode);
      if ( false === $row ) {
        return false;
      }
      $state = self::state_with_events($row['state'] ?? array(), $events);
      if ( false === $state ) {
        return false;
      }
      $result = $storage->write_result($account, $barcode, $state, null === $row ? null : $row['revision']);
      if ( null !== $result ) {
        return $result;
      }
      if ( null === $storage->has_link($barcode) ) {
        // The linked-pending scan can be stale. Leave its events in pending.
        return null;
      }
    }
    return false;
  }

  private static function save_page_state( $storage, $account, $barcode, $state, $revision )
  {
    $events = $state['events'];
    for ( $attempt = 0; $attempt < 3; $attempt++ ) {
      if ( $attempt > 0 ) {
        $row = $storage->read($account, $barcode);
        if ( ! is_array($row) ) {
          return $row;
        }
        $revision = $row['revision'];
        $state = self::state_with_normalized_events($row['state'], $events);
        if ( false === $state ) {
          return false;
        }
      }
      $result = $storage->write_result($account, $barcode, $state, $revision);
      if ( null !== $result ) {
        return $result;
      }
    }
    return null;
  }

  private static function state_with_events( $state, $events )
  {
    if ( empty($events) ) {
      return $state;
    }
    $normalized_events = array();
    foreach ( $events as $event ) {
      $normalized = self::normalize_event($event);
      if ( false === $normalized ) {
        return false;
      }
      $normalized_events[] = $normalized;
    }
    return self::state_with_normalized_events($state, $normalized_events);
  }

  private static function state_with_normalized_events( $state, $events )
  {
    $stored = $state['events'] ?? array();
    if ( ! is_array($stored) ) {
      return false;
    }
    $history = array();
    foreach ( $stored as $event ) {
      if ( ! is_array($event) || ! isset($event['event_id'], $event['event_code'])
        || ! is_string($event['event_id']) || false === self::event_id($event['event_id'])
        || ! is_string($event['event_code']) || '' === $event['event_code'] ) {
        return false;
      }
      $history[$event['event_id']] = $event;
    }
    foreach ( $events as $event ) {
      $history[$event['event_id']] = $history[$event['event_id']] ?? $event;
    }
    $history = array_values($history);
    usort($history, function( $left, $right ) {
      return self::compare_ids($left['event_id'], $right['event_id']);
    });

    // Replay in ID order so an older pending event can change the latest status and warnings.
    $summary = ! empty($state['registered_locally']) ? array('registered_locally' => true, 'shipment_status' => 'registered') : array();
    foreach ( $history as $event ) {
      $summary = self::reduce_event($summary, $event['event_code']);
    }
    $summary['events'] = $history;
    return $summary;
  }

  private static function normalize_event( $event )
  {
    if ( ! is_array($event) || ! isset($event['eventId'], $event['eventCode']) ) {
      return false;
    }
    $id = self::event_id($event['eventId']);
    if ( false === $id || ! is_string($event['eventCode']) || '' === $event['eventCode'] ) {
      return false;
    }
    return array(
      'event_id' => $id,
      'event_code' => $event['eventCode'],
      'event_name' => isset($event['eventName']) && is_string($event['eventName']) ? $event['eventName'] : '',
      'event_date' => isset($event['eventDate']) && is_string($event['eventDate']) ? $event['eventDate'] : '',
      'location_name' => isset($event['location']['locationName']) && is_string($event['location']['locationName']) ? $event['location']['locationName'] : '',
    );
  }

  private static function reduce_event( $state, $code )
  {
    if ( ! isset($state['warnings']) || ! is_array($state['warnings']) ) {
      $state['warnings'] = array();
    }

    if ( in_array($code, array('TRT_SHIPMENT_PICKED_UP_BY_COURIER', 'TRT_SHIPMENT_DEPARTED_FROM_PM', 'TRT_SHIPMENT_ARRIVED_TO_PO', 'TRT_SHIPMENT_COURIER_DELIVERY_STARTED'), true) ) {
      unset($state['warnings']['missing']);
    }

    if ( 'TRT_SHIPMENT_RETURN' === $code ) {
      $state['returning'] = true;
      $state['shipment_status'] = 'returning';
      $state['warnings'] = array();
    } elseif ( in_array($code, array('TRT_SHIPMENT_DELIVERED', 'TRT_SHIPMENT_EMI'), true) ) {
      $state['shipment_status'] = ! empty($state['returning']) ? 'returned' : 'delivered';
      $state['warnings'] = array();
    } elseif ( 'TRT_SHIPMENT_CANCELLED' === $code ) {
      $state['shipment_status'] = 'cancelled';
      $state['warnings'] = array();
    } elseif ( 'TRT_SHIPMENT_MOVEMENT_STOPPED' === $code ) {
      if ( empty($state['shipment_status']) ) { $state['shipment_status'] = 'processing'; }
      $state['warnings']['stopped'] = true;
    } elseif ( 'TRT_SHIPMENT_MOVEMENT_RESTORED' === $code ) {
      unset($state['warnings']['stopped']);
    } elseif ( 'TRT_SHIPMENT_MISSING_IN_PM' === $code ) {
      if ( empty($state['shipment_status']) ) { $state['shipment_status'] = 'processing'; }
      $state['warnings']['missing'] = true;
    } elseif ( ! empty($state['returning']) ) {
      // Informational and movement events do not undo the return journey.
    } elseif ( 'TRT_SHIPMENT_REGISTERED' === $code ) {
      $state['shipment_status'] = 'registered';
    } elseif ( 'TRT_SHIPMENT_STORED_IN_PM' === $code ) {
      $state['shipment_status'] = 'parcel_machine';
      unset($state['warnings']['missing']);
    } elseif ( 'TRT_SHIPMENT_ARRIVED_TO_FINAL_PO' === $code ) {
      $state['shipment_status'] = 'post_office';
      unset($state['warnings']['missing']);
    } elseif ( 'TRT_SHIPMENT_EDH' === $code ) {
      $state['shipment_status'] = 'pickup';
      unset($state['warnings']['missing']);
    } elseif ( in_array($code, array(
      'TRT_SHIPMENT_HANDOVER_TO_PM', 'TRT_SHIPMENT_PICKED_UP_BY_COURIER',
      'TRT_SHIPMENT_HANDOVER_TO_PO', 'TRT_SHIPMENT_DEPARTED_FROM_PM',
      'TRT_SHIPMENT_ARRIVED_TO_PO', 'TRT_SHIPMENT_COURIER_DELIVERY_STARTED',
      'TRT_SHIPMENT_REGISTERED_IN_OMNIVA',
      'TRT_SHIPMENT_EDA', 'TRT_SHIPMENT_EDB', 'TRT_SHIPMENT_EDC',
      'TRT_SHIPMENT_EDD', 'TRT_SHIPMENT_EDF', 'TRT_SHIPMENT_EDG',
      'TRT_SHIPMENT_EMA', 'TRT_SHIPMENT_EMB', 'TRT_SHIPMENT_EMC',
      'TRT_SHIPMENT_EMD', 'TRT_SHIPMENT_EMF', 'TRT_SHIPMENT_EMG',
      'TRT_SHIPMENT_EMJ',
    ), true) ) {
      $state['shipment_status'] = 'processing';
      unset($state['warnings']['missing']);
    }
    return $state;
  }

  public static function event_id( $value )
  {
    if ( ! is_string($value) && ! is_int($value) ) {
      return false;
    }
    $value = (string) $value;
    if ( ! preg_match('/^[0-9]{1,32}$/D', $value) ) {
      return false;
    }
    $value = ltrim($value, '0');
    return '' === $value ? '0' : $value;
  }

  public static function compare_ids( $left, $right )
  {
    if ( strlen($left) !== strlen($right) ) {
      return strlen($left) < strlen($right) ? -1 : 1;
    }
    return strcmp($left, $right);
  }


  public static function process_page( $events, $load_state, $save_state, $save_cursor, $save_unmatched = null )
  {
    if ( ! is_array($events) || ! is_callable($load_state) || ! is_callable($save_state) || ! is_callable($save_cursor) ) {
      return false;
    }
    $states = array();
    $by_barcode = array();
    $previous = false;
    foreach ( $events as $event ) {
      $id = self::event_id(is_array($event) ? ($event['eventId'] ?? null) : null);
      if ( false === $id ) {
        continue;
      }
      if ( false !== $previous && self::compare_ids($id, $previous) <= 0 ) {
        return false;
      }
      // A usable ID can advance the cursor even when its shipment data is malformed.
      $previous = $id;
      $barcode = OmnivaLt_Tracking_Storage::normalize_barcode($event['shipmentBarcode'] ?? null);
      if ( false === $barcode || ! isset($event['eventCode']) || ! is_string($event['eventCode']) || '' === trim($event['eventCode']) ) {
        continue;
      }
      if ( ! array_key_exists($barcode, $states) ) {
        // Null means the barcode is not owned by this shop; false indicates failure.
        $state = call_user_func($load_state, $barcode);
        if ( false === $state || ( null !== $state && ! is_array($state) ) ) {
          return false;
        }
        $states[$barcode] = $state;
      }
      if ( null !== $states[$barcode] ) {
        $by_barcode[$barcode][] = $event;
      } elseif ( null !== $save_unmatched && ( ! is_callable($save_unmatched) || true !== call_user_func($save_unmatched, $event) ) ) {
        return false;
      }
    }

    foreach ( $by_barcode as $barcode => $shipment_events ) {
      $state = self::state_with_events($states[$barcode], $shipment_events);
      if ( false === $state || true !== call_user_func($save_state, (string) $barcode, $state) ) {
        return false;
      }
    }
    return empty($events) || (false !== $previous && true === call_user_func($save_cursor, $previous));
  }
}
