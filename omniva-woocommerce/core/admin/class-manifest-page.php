<?php
class OmnivaLt_Manifest_Page
{
  private const BARCODE_LOOKUP_CHUNK_SIZE = 100;

  private static $page_rendered = false;

  public static function load_admin_scripts()
  {
    $folder_css = '/assets/css/';
    $folder_js = '/assets/js/';

    // The plugin entry point defines OMNIVALT_DIR before this controller is loaded.
    // @phpstan-ignore constant.notFound
    wp_enqueue_style('omnivalt_admin_manifest', plugins_url($folder_css . 'omniva_admin_manifest.css', OmnivaLt_Core::$main_file_path), array(), filemtime(OMNIVALT_DIR . 'assets/css/omniva_admin_manifest.css'));
    wp_enqueue_style('bootstrap-datetimepicker', plugins_url($folder_js . 'datetimepicker/bootstrap-datetimepicker.min.css', OmnivaLt_Core::$main_file_path));

    wp_enqueue_script('moment', plugins_url($folder_js . 'moment.min.js', OmnivaLt_Core::$main_file_path), array(), null, true);
    wp_enqueue_script('bootstrap-datetimepicker', plugins_url($folder_js . 'datetimepicker/bootstrap-datetimepicker.min.js', OmnivaLt_Core::$main_file_path), array('jquery', 'moment'), null, true);
    wp_enqueue_script('omniva_helper', plugins_url($folder_js . 'omniva_helper.js', OmnivaLt_Core::$main_file_path), array(), null, true);
    // @phpstan-ignore constant.notFound
    wp_enqueue_script('omniva_manifest', plugins_url($folder_js . 'omniva_manifest.js', OmnivaLt_Core::$main_file_path), array('jquery'), filemtime(OMNIVALT_DIR . 'assets/js/omniva_manifest.js'), true);
    wp_localize_script('omniva_manifest', 'omnivaltTracking', array(
      'url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('omnivalt_tracking'),
      'loading' => __('Loading…', 'omnivalt'), 'error' => __('Tracking could not be loaded. Please try again later.', 'omnivalt'),
      'title' => __('Shipment tracking', 'omnivalt'), 'close' => __('Close', 'omnivalt'),
      'historyTitle' => __('Shipment history', 'omnivalt'), 'destination' => __('Delivery location', 'omnivalt'),
      'empty' => __('No synchronized tracking events yet.', 'omnivalt'),
      'continuing' => __('Synchronization continues in the background.', 'omnivalt'),
      'listReady' => __('Shipment statuses have been updated. Refresh the list to apply your current filters.', 'omnivalt'),
      'refreshList' => __('Refresh list', 'omnivalt'),
    ));

    OmnivaLt_Admin_Page_Assets::add_readiness_fallback(
      'omniva_manifest',
      'omnivalt-manifest-root',
      'is-ready',
      'add'
    );

    wp_localize_script('omniva_manifest', 'omnivaglobals', array(
      'cookie_checked_list' => 'omniva_checked',
      'ajax_url' => admin_url('admin-ajax.php'),
      'remove_courier_call_nonce' => wp_create_nonce('omnivalt_remove_courier_call'),
    ));

    wp_localize_script('omniva_manifest', 'omnivatext', array(
      'alert_select_orders' => __('Please select orders', 'omnivalt'),
    ));
  }

  public static function render_page()
  {
    if ( ! current_user_can('manage_woocommerce') || self::$page_rendered ) {
      return;
    }

    // Match the old include_once behaviour, including re-entrant page callbacks.
    self::$page_rendered = true;

    $page_data = self::prepare_page_data();

    // Keep the existing extension point and its execution order before the page markup.
    do_action('omniva_admin_manifest_head');

    include_once OMNIVALT_DIR . 'templates/admin/manifest-page.php';
  }

  private static function prepare_page_data()
  {
    $shipping_settings = OmnivaLt_Core::get_settings();
    $tracking_account = $shipping_settings ? OmnivaLt_Tracking_Sync::account() : false;
    $configs = OmnivaLt_Core::get_configs();
    $page_params = OmnivaLt_Manifest::page_params();
    $orders_data = OmnivaLt_Manifest::page_get_orders();
    $order_details = array();
    $tracking_barcodes = array();
    foreach ( $shipping_settings ? ($orders_data['orders'] ?? array()) : array() as $order ) {
      $order_id = $order->get_id();
      $details = OmnivaLt_Wc_Order::get_data($order_id);
      $order_details[$order_id] = $details;
      foreach ( (array) ($details->omniva->barcodes ?? array()) as $barcode ) {
        $tracking_barcodes[] = $barcode;
      }
    }
    $selected_orders = self::get_selected_orders();
    $sender_info_complete = OmnivaLt_Helper::has_required_sender_information($shipping_settings);
    $manifest_enabled = (!isset($shipping_settings['manifest_enable']) || $shipping_settings['manifest_enable'] === 'yes') ? true : false;
    $selected_order_has_barcodes = array();
    if ( ! $sender_info_complete || $manifest_enabled ) {
      $selected_order_has_barcodes = self::get_selected_order_barcode_state($selected_orders);
    }
    $active_omx = ($configs['api']['type'] === 'omx');
    $current_courier_calls = OmnivaLt_Helper::get_courier_calls();

    $active_filter_count = 0;
    $active_filters = (isset($orders_data['filters']) && is_array($orders_data['filters'])) ? $orders_data['filters'] : array();
    foreach ( array('id', 'customer', 'barcode', 'status', 'shipment_status') as $filter_key ) {
      $filter_value = isset($active_filters[$filter_key]) ? $active_filters[$filter_key] : false;
      if (false !== $filter_value && null !== $filter_value && '' !== trim((string) $filter_value) && ('status' !== $filter_key || '-1' !== (string) $filter_value)) {
        $active_filter_count++;
      }
    }

    foreach ( array('start_date', 'end_date') as $date_filter_key ) {
      if (isset($active_filters[$date_filter_key]) && '' !== trim((string) $active_filters[$date_filter_key])) {
        $active_filter_count++;
        break;
      }
    }

    $is_wrong_timezone = (OmnivaLt_Helper::get_timezone_offset(OmnivaLt_Helper::get_local_timezone_string()) !== OmnivaLt_Helper::get_timezone_offset('Europe/Tallinn'));
    $timezone_alert = __('The offset of the timezone of your website is different from the offset of the timezone of the Omniva server, so the courier call time is displayed differently than specified in the settings', 'omnivalt');

    return array(
      'shipping_settings' => $shipping_settings,
      'show_tracking_controls' => false !== $tracking_account,
      'tracking_status' => $tracking_account ? self::tracking_sync_status($tracking_account) : array(),
      'tracking_rows' => $tracking_account ? self::load_tracking_rows($tracking_account['id'], $tracking_barcodes) : array(),
      'order_details' => $order_details,
      'configs' => $configs,
      'page_params' => $page_params,
      'orders_data' => $orders_data,
      'selected_orders' => $selected_orders,
      'selected_order_has_barcodes' => $selected_order_has_barcodes,
      'sender_info_complete' => $sender_info_complete,
      'manifest_enabled' => $manifest_enabled,
      'active_omx' => $active_omx,
      'current_courier_calls' => $current_courier_calls,
      'active_filter_count' => $active_filter_count,
      'is_wrong_timezone' => $is_wrong_timezone,
      'timezone_alert' => $timezone_alert,
    );
  }

  private static function get_selected_order_barcode_state( $selected_orders )
  {
    $barcode_state = array();
    if ( ! is_array($selected_orders) ) {
      return $barcode_state;
    }

    $selected_orders = array_values(array_unique($selected_orders));

    if ( empty($selected_orders) ) {
      return $barcode_state;
    }

    $meta_keys = OmnivaLt_Core::get_configs('meta_keys');
    if ( ! is_array($meta_keys) || empty($meta_keys['barcodes']) || ! is_string($meta_keys['barcodes']) ) {
      return $barcode_state;
    }

    $selected_order_count = count($selected_orders);
    $orders_with_barcodes_count = 0;

    foreach ( $selected_orders as $order_id ) {
      $barcode_state[(string) $order_id] = false;
    }

    foreach ( array_chunk($selected_orders, self::BARCODE_LOOKUP_CHUNK_SIZE) as $order_chunk ) {
      $orders = wc_get_orders(array(
        'post__in' => $order_chunk,
        'limit' => count($order_chunk),
        'paginate' => false,
        'return' => 'objects',
      ));

      if ( ! is_array($orders) ) {
        continue;
      }

      foreach ( $orders as $order ) {
        if ( ! is_object($order) || ! method_exists($order, 'get_id') || ! method_exists($order, 'get_meta') ) {
          continue;
        }

        $order_id = $order->get_id();
        $order_key = (string) $order_id;
        if ( isset($barcode_state[$order_key]) && ! $barcode_state[$order_key]
          && self::has_barcodes($order->get_meta($meta_keys['barcodes'], true)) ) {
          $barcode_state[$order_key] = true;
          $orders_with_barcodes_count++;
        }
      }

      if ( $orders_with_barcodes_count === $selected_order_count ) {
        break;
      }
    }

    return $barcode_state;
  }

  private static function has_barcodes( $barcodes )
  {
    if ( ! is_array($barcodes) ) {
      return is_scalar($barcodes) && '' !== trim((string) $barcodes);
    }

    foreach ( $barcodes as $barcode ) {
      if ( is_scalar($barcode) && '' !== trim((string) $barcode) ) {
        return true;
      }
    }

    return false;
  }

  private static function get_selected_orders()
  {
    if ( ! isset($_COOKIE['omniva_checked']) ) {
      return array();
    }

    if ( ! is_string($_COOKIE['omniva_checked']) ) {
      return array();
    }

    $cookie_value = json_decode(sanitize_text_field(wp_unslash($_COOKIE['omniva_checked'])), true);
    if ( ! is_array($cookie_value) ) {
      return array();
    }

    $selected_orders = array();
    foreach ( $cookie_value as $order_id ) {
      if ( ! is_string($order_id) && ! is_int($order_id) ) {
        continue;
      }

      $order_id = absint($order_id);
      if ( $order_id > 0 ) {
        $selected_orders[] = $order_id;
      }
    }

    return array_values(array_unique($selected_orders));
  }

  public static function tracking_labels()
  {
    return array(
      'registered' => __('Registered', 'omnivalt'), 'processing' => __('Processing', 'omnivalt'),
      'parcel_machine' => __('In parcel machine', 'omnivalt'), 'post_office' => __('At post office', 'omnivalt'),
      'pickup' => __('Ready for pickup', 'omnivalt'), 'delivered' => __('Delivered', 'omnivalt'),
      'returning' => __('Returning', 'omnivalt'), 'returned' => __('Returned', 'omnivalt'), 'cancelled' => __('Cancelled', 'omnivalt'),
    );
  }

  private static function format_tracking_event_date( $value )
  {
    if ( ! is_string($value) || '' === trim($value)
      || 1 !== preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:?\d{2})?$/D', trim($value)) ) {
      return is_string($value) ? $value : '';
    }
    try {
      // OMX can omit the timezone from UTC event timestamps.
      $date = new DateTimeImmutable(trim($value), new DateTimeZone('UTC'));
      return wp_date(get_option('date_format') . ' ' . get_option('time_format'), $date->getTimestamp(), wp_timezone());
    } catch ( \Throwable $e ) {
      return $value;
    }
  }

  private static function load_tracking_rows( $account, $barcodes )
  {
    $storage = OmnivaLt_Tracking_Sync::storage();
    return ! empty($barcodes) && $storage->exists() ? $storage->read_display_batch($account, $barcodes) : array();
  }

  public static function tracking_button( $order_id, $barcode, $row )
  {
    if ( ! is_string($barcode) ) {
      return;
    }
    echo '<span class="omnivalt-tracking-slot" data-order="' . esc_attr($order_id) . '" data-barcode="' . esc_attr($barcode) . '">';
    if ( is_array($row) && ! empty($row['shipment_status']) ) {
      $state = $row['state'];
      $labels = OmnivaLt_Manifest_Page::tracking_labels();
      if ( isset($labels[$row['shipment_status']]) ) {
        $event = OmnivaLt_Tracking_Storage::latest_event($state);
        $event_name = empty($event['event_id']) ? __('Shipment registered', 'omnivalt') : (isset($event['event_name']) && is_string($event['event_name']) ? $event['event_name'] : '');
        $event_date = self::format_tracking_event_date(empty($event) ? ($state['registered_at'] ?? '') : ($event['event_date'] ?? ''));
        $location_name = isset($event['location_name']) && is_string($event['location_name']) ? trim($event['location_name']) : '';
        $warnings = array();
        if ( ! empty($state['warnings']['stopped']) ) {
          $warnings[] = __('Shipment movement stopped', 'omnivalt');
        }
        if ( ! empty($state['warnings']['missing']) ) {
          $warnings[] = __('Shipment not found in parcel machine', 'omnivalt');
        }
        $tip = implode("\n", array_merge(array_filter(array($event_name, $event_date, $location_name)), $warnings));
        echo '<button type="button" class="omnivalt-tracking-status omnivalt-tracking-status--' . esc_attr($row['shipment_status']) . '" data-tracking-status="' . esc_attr($row['shipment_status']) . '" data-tracking-label="' . esc_attr($labels[$row['shipment_status']]) . '" data-tooltip="' . esc_attr($tip) . '" data-tracking-event="' . esc_attr($event_name) . '" data-tracking-date="' . esc_attr($event_date) . '" data-tracking-location="' . esc_attr($location_name) . '" data-tracking-warnings="' . esc_attr(implode("\n", $warnings)) . '" aria-label="' . esc_attr($labels[$row['shipment_status']] . '. ' . $tip) . '">' . esc_html($labels[$row['shipment_status']]) . (! empty($state['warnings']) ? ' <span aria-hidden="true">!</span>' : '') . '</button>';
      }
    }
    echo '</span>';
  }

  public static function tracking_request()
  {
    // The server method must match POST exactly.
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    if ( 'POST' !== ($_SERVER['REQUEST_METHOD'] ?? '') || ! current_user_can('manage_woocommerce') ) {
      wp_send_json_error(array('message' => __('Access denied.', 'omnivalt')), 403);
    }
    check_ajax_referer('omnivalt_tracking', 'nonce');
    $operation = isset($_POST['operation']) && is_string($_POST['operation']) ? sanitize_key(wp_unslash($_POST['operation'])) : '';
    $account = OmnivaLt_Tracking_Sync::account();
    if ( ! $account ) {
      wp_send_json_error(array('message' => __('Omniva credentials are missing.', 'omnivalt')), 400);
    }
    if ( 'sync' === $operation ) {
      if ( ! OmnivaLt_Tracking_Sync::links_ready() ) {
        OmnivaLt_Tracking_Sync::ensure_scheduled();
        wp_send_json_success(self::tracking_sync_status($account));
      }
      if ( ! OmnivaLt_Tracking_Sync::run(true) ) {
        wp_send_json_error(array('message' => __('Synchronization is already running. Please try again later.', 'omnivalt')), 409);
      }
      wp_send_json_success(OmnivaLt_Manifest_Page::tracking_sync_status($account));
    }
    if ( 'status' === $operation ) {
      wp_send_json_success(OmnivaLt_Manifest_Page::tracking_sync_status($account));
    }
    if ( 'history' === $operation ) {
      // The history handler checks the exact order ID and validates the normalized barcode.
      // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
      $order = isset($_POST['order']) && is_string($_POST['order']) ? wp_unslash($_POST['order']) : '';
      // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
      $barcode = isset($_POST['barcode']) && is_string($_POST['barcode']) ? wp_unslash($_POST['barcode']) : '';
      self::tracking_history($account['id'], $order, $barcode);
    }
    if ( 'rows' === $operation ) {
      // Each row is type-checked and its fields are sanitized before use below.
      // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
      $rows = isset($_POST['rows']) && is_array($_POST['rows']) ? wp_unslash($_POST['rows']) : array();
      if ( count($rows) > 100 ) {
        wp_send_json_error(array(), 400);
      }
      $order_barcodes = array();
      $valid_rows = array();
      foreach ( $rows as $row ) {
        if ( ! is_array($row) || ! isset($row['order'], $row['barcode']) || ! is_scalar($row['order']) || ! is_string($row['barcode']) ) {
          continue;
        }
        $id = absint($row['order']);
        $barcode = sanitize_text_field($row['barcode']);
        $key = OmnivaLt_Tracking_Storage::normalize_barcode($barcode);
        if ( false === $key ) {
          continue;
        }
        if ( ! array_key_exists($id, $order_barcodes) ) {
          $order_barcodes[$id] = self::tracking_order_barcode_keys($id);
        }
        if ( in_array($key, $order_barcodes[$id], true) ) {
          $valid_rows[] = array('order' => $id, 'barcode' => $barcode);
        }
      }
      $states = self::load_tracking_rows($account['id'], array_column($valid_rows, 'barcode'));
      $html = array();
      foreach ( $valid_rows as $row ) {
        ob_start();
        self::tracking_button($row['order'], $row['barcode'], $states[$row['barcode']] ?? null);
        $row['html'] = ob_get_clean();
        $html[] = $row;
      }
      wp_send_json_success($html);
    }
    if ( 'dismiss_gap' === $operation ) {
      $state = get_option('omnivalt_tracking_sync_' . $account['id'], array());
      $gap_id = self::tracking_gap_id($state);
      // Require an exact match with the stored ID and persist only the stored value.
      // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
      $requested = isset($_POST['gap_id']) && is_string($_POST['gap_id']) ? wp_unslash($_POST['gap_id']) : '';
      if ( '' === $gap_id || $requested !== $gap_id ) {
        wp_send_json_error(array('message' => __('The tracking warning has changed. Reload the page and try again.', 'omnivalt')), 409);
      }
      $key = 'omnivalt_tracking_dismissed_gap_' . $account['id'];
      update_option($key, $gap_id, false);
      if ( get_option($key) !== $gap_id ) {
        wp_send_json_error(array('message' => __('The tracking warning could not be dismissed.', 'omnivalt')), 500);
      }
      wp_send_json_success();
    }
    wp_send_json_error(array('message' => __('Invalid request.', 'omnivalt')), 400);
  }

  private static function tracking_order_barcode_keys( $order_id )
  {
    $barcodes = OmnivaLt_Omniva_Order::get_barcodes($order_id);
    return is_array($barcodes) ? array_map(array('OmnivaLt_Tracking_Storage', 'normalize_barcode'), $barcodes) : array();
  }

  private static function tracking_history( $account_id, $order, $barcode )
  {
    $barcode = OmnivaLt_Tracking_Storage::normalize_barcode($barcode);
    $order_id = absint($order);
    if ( $order_id < 1 || (string) $order_id !== $order || false === $barcode ) {
      wp_send_json_error(array('message' => __('Invalid request.', 'omnivalt')), 400);
    }
    if ( ! in_array($barcode, self::tracking_order_barcode_keys($order_id), true) ) {
      wp_send_json_error(array('message' => __('Shipment not found.', 'omnivalt')), 404);
    }

    $storage = OmnivaLt_Tracking_Sync::storage();
    $row = $storage->exists() ? $storage->read_for_display($account_id, $barcode) : false;
    $history = $row['state']['events'] ?? array();
    $error = array('message' => __('Tracking could not be loaded. Please try again later.', 'omnivalt'));
    if ( false === $row || ! is_array($history) ) {
      wp_send_json_error($error, 500);
    }
    $events = array();
    foreach ( $history as $event ) {
      if ( ! is_array($event) || ! isset($event['event_code']) || ! is_string($event['event_code']) ) {
        wp_send_json_error($error, 500);
      }
      $events[] = array(
        'name' => isset($event['event_name']) && is_string($event['event_name']) && '' !== $event['event_name'] ? $event['event_name'] : $event['event_code'],
        'date' => self::format_tracking_event_date($event['event_date'] ?? ''),
        'location' => isset($event['location_name']) && is_string($event['location_name']) ? $event['location_name'] : '',
      );
    }
    if ( empty($events) && 'registered' === ($row['shipment_status'] ?? '') && ! empty($row['state']['registered_locally']) ) {
      $events[] = array(
        'name' => __('Shipment registered', 'omnivalt'),
        'date' => self::format_tracking_event_date($row['state']['registered_at'] ?? ''),
        'location' => '',
      );
    }
    wp_send_json_success(array('events' => $events));
  }

  private static function tracking_gap_id( $state )
  {
    if ( ! is_array($state) || empty($state['gap']) ) {
      return '';
    }
    return isset($state['gap_id']) && is_string($state['gap_id']) && '' !== $state['gap_id'] ? $state['gap_id'] : 'legacy';
  }

  public static function tracking_sync_status( $account )
  {
    if ( ! OmnivaLt_Tracking_Sync::links_ready() ) {
      return array(
        'last_success' => 0,
        'label' => __('Synchronizing…', 'omnivalt'),
        'preparing' => true,
        'retry_after' => 0,
        'failure_message' => '',
        'error' => false, 'failed' => false, 'gap' => false, 'complete' => true,
        'gap_id' => '',
      );
    }
    $state = get_option('omnivalt_tracking_sync_' . $account['id'], array());
    $state = is_array($state) ? $state : array();
    $gap_id = self::tracking_gap_id($state);
    return array(
      'last_success' => $state['last_success'] ?? 0,
      'label' => empty($state['last_success']) ? __('Not synchronized yet', 'omnivalt') : sprintf(__('Updated %s ago', 'omnivalt'), human_time_diff($state['last_success'], time())),
      'error' => ! empty($state['failed_since']) && $state['failed_since'] <= time() - DAY_IN_SECONDS,
      'failed' => ! empty($state['failed_since']),
      'failure_message' => ! empty($state['failed_since']) ? self::tracking_failure_message($state['last_failure'] ?? array()) : '',
      'preparing' => false,
      'retry_after' => OmnivaLt_Tracking_Sync::manual_retry_after($state),
      'gap' => '' !== $gap_id && $gap_id !== get_option('omnivalt_tracking_dismissed_gap_' . $account['id'], ''),
      'gap_id' => $gap_id,
      'complete' => empty($state['in_progress']),
    );
  }

  private static function tracking_failure_message( $failure )
  {
    if ( ! is_array($failure) ) {
      return '';
    }
    switch ( $failure['code'] ?? '' ) {
      case 'transport':
        return __('Shipment status synchronization could not connect to Omniva.', 'omnivalt');
      case 'http':
        $status = $failure['http_status'] ?? 0;
        if ( is_int($status) && $status >= 100 && $status <= 599 ) {
          return sprintf(__('The Omniva tracking API returned HTTP status %d.', 'omnivalt'), $status);
        }
        return __('The Omniva tracking API returned an HTTP error.', 'omnivalt');
      case 'invalid_response':
      case 'invalid_cursor_response':
      case 'unordered_events':
        return __('The Omniva tracking API returned unexpected event data.', 'omnivalt');
      case 'invalid_cursor':
      case 'invalid_timestamp':
        return __('Shipment status synchronization has an invalid saved position.', 'omnivalt');
      case 'missing_credentials':
        return __('Omniva tracking credentials are missing.', 'omnivalt');
      case 'storage':
        return __('Shipment status synchronization encountered a database error.', 'omnivalt');
      case 'write_conflict':
        return __('Shipment tracking changed during synchronization. The update will be retried.', 'omnivalt');
    }
    return '';
  }
}
