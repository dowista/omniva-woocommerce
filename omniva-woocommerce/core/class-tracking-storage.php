<?php

/** Tracking data is kept apart from WooCommerce's order storage. */
class OmnivaLt_Tracking_Storage
{
  private $db;
  private $states_table;
  private $links_table;
  private $pending_table;
  private $link_state_lock;
  private $multiple_locks;
  private static $held_locks = array();

  public function __construct( $db )
  {
    $this->db = $db;
    $this->states_table = $db->prefix . 'omnivalt_tracking_state';
    $this->links_table = $db->prefix . 'omnivalt_tracking_order';
    $this->pending_table = $db->prefix . 'omnivalt_tracking_pending';
    // DB_NAME is supplied by the site's WordPress configuration.
    $this->link_state_lock = 'omnivalt-link-state-' . substr(hash('sha256', DB_NAME . ':' . $this->links_table), 0, 32); // @phpstan-ignore constant.notFound
  }

  public function install()
  {
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset = $this->db->get_charset_collate();
    dbDelta("CREATE TABLE {$this->states_table} (
      account char(64) NOT NULL,
      barcode varchar(64) NOT NULL,
      state longtext NOT NULL,
      revision bigint unsigned NOT NULL DEFAULT 0,
      PRIMARY KEY  (account,barcode),
      KEY barcode (barcode)
    ) $charset;");
    dbDelta("CREATE TABLE {$this->links_table} (
      order_id bigint unsigned NOT NULL,
      barcode varchar(64) NOT NULL,
      PRIMARY KEY  (order_id,barcode),
      KEY barcode (barcode)
    ) $charset;");
    dbDelta("CREATE TABLE {$this->pending_table} (
      account char(64) NOT NULL,
      barcode varchar(64) NOT NULL,
      event_id varchar(32) NOT NULL,
      event_data longtext NOT NULL,
      received bigint unsigned NOT NULL,
      PRIMARY KEY  (account,event_id),
      KEY barcode (barcode),
      KEY received (received)
    ) $charset;");
    return $this->exists();
  }

  public function exists()
  {
    foreach ( array($this->states_table, $this->links_table, $this->pending_table) as $table ) {
      $found = $this->db->get_var($this->db->prepare('SHOW TABLES LIKE %s', $this->db->esc_like($table)));
      if ( $found !== $table ) {
        return false;
      }
    }
    return true;
  }

  public function save_pending( $account, $event )
  {
    if ( ! $this->valid_account($account) || ! is_array($event) || ! isset($event['eventId'], $event['eventCode'])
      || ! is_string($event['eventCode']) ) {
      return false;
    }
    $barcode = self::normalize_barcode($event['shipmentBarcode'] ?? null);
    $id = OmnivaLt_Tracking_Sync::event_id($event['eventId']);
    if ( false === $barcode || false === $id ) {
      return false;
    }
    $data = array(
      'eventId' => $id,
      'eventCode' => $event['eventCode'],
      'shipmentBarcode' => $barcode,
      'eventName' => isset($event['eventName']) && is_string($event['eventName']) ? $event['eventName'] : '',
      'eventDate' => isset($event['eventDate']) && is_string($event['eventDate']) ? $event['eventDate'] : '',
    );
    if ( isset($event['location']['locationName']) && is_string($event['location']['locationName']) ) {
      $data['location'] = array('locationName' => $event['location']['locationName']);
    }
    $json = wp_json_encode($data);
    if ( ! is_string($json) ) {
      return false;
    }
    return false !== $this->write_tracking_json($this->db->prepare(
      "INSERT INTO {$this->pending_table} (account, barcode, event_id, event_data, received) VALUES (%s, %s, %s, %s, %d) ON DUPLICATE KEY UPDATE event_id = event_id",
      $account, $barcode, $id, $json, time()
    ));
  }

  public function pending( $account, $barcode )
  {
    $barcode = self::normalize_barcode($barcode);
    if ( ! $this->valid_account($account) || false === $barcode ) {
      return false;
    }
    $rows = $this->db->get_col($this->db->prepare(
      "SELECT event_data FROM {$this->pending_table} WHERE account = %s AND barcode = %s ORDER BY LENGTH(event_id), event_id",
      $account, $barcode
    ));
    if ( '' !== $this->db->last_error || ! is_array($rows) ) {
      return false;
    }
    $events = array();
    foreach ( $rows as $row ) {
      $event = json_decode($row, true);
      if ( ! is_array($event) || JSON_ERROR_NONE !== json_last_error() ) {
        return false;
      }
      $events[] = $event;
    }
    return $events;
  }

  public function linked_pending_barcodes( $account )
  {
    if ( ! $this->valid_account($account) ) {
      return false;
    }
    $rows = $this->db->get_col($this->db->prepare(
      "SELECT DISTINCT p.barcode FROM {$this->pending_table} p INNER JOIN {$this->links_table} l ON p.barcode = l.barcode WHERE p.account = %s LIMIT 100",
      $account
    ));
    return '' === $this->db->last_error && is_array($rows) ? $rows : false;
  }

  public function delete_pending_through( $account, $barcode, $id )
  {
    $barcode = self::normalize_barcode($barcode);
    $id = OmnivaLt_Tracking_Sync::event_id($id);
    if ( ! $this->valid_account($account) || false === $barcode || false === $id ) {
      return false;
    }
    return false !== $this->db->query($this->db->prepare(
      "DELETE FROM {$this->pending_table} WHERE account = %s AND barcode = %s AND (LENGTH(event_id) < %d OR (LENGTH(event_id) = %d AND event_id <= %s))",
      $account, $barcode, strlen($id), strlen($id), $id
    ));
  }

  public function purge_pending( $before )
  {
    if ( ! is_int($before) || $before < 1 ) {
      return false;
    }
    return false !== $this->db->query($this->db->prepare("DELETE FROM {$this->pending_table} WHERE received < %d", $before));
  }

  public function link( $order_id, $barcode )
  {
    $barcode = self::normalize_barcode($barcode);
    if ( ! $this->valid_order($order_id) || false === $barcode ) {
      return false;
    }
    return $this->change_links(
      "INSERT INTO {$this->links_table} (order_id, barcode)
      SELECT %d, %s FROM DUAL WHERE",
      array($order_id, $barcode),
      ' ON DUPLICATE KEY UPDATE order_id = order_id'
    );
  }

  public function has_link( $barcode )
  {
    $barcode = self::normalize_barcode($barcode);
    if ( false === $barcode ) {
      return false;
    }
    $found = $this->db->get_var($this->db->prepare("SELECT order_id FROM {$this->links_table} WHERE barcode = %s LIMIT 1", $barcode));
    if ( '' !== $this->db->last_error ) {
      return false;
    }
    return null === $found ? null : true;
  }

  public function unlink_replaced_barcodes( $order_id, $previous_barcodes, $barcodes )
  {
    if ( ! $this->valid_order($order_id) || ! is_array($previous_barcodes) || ! is_array($barcodes) || empty($barcodes) ) {
      return false;
    }
    $barcodes = array_map(array(self::class, 'normalize_barcode'), $barcodes);
    if ( in_array(false, $barcodes, true) ) {
      return false;
    }
    $previous_barcodes = array_map(array(self::class, 'normalize_barcode'), $previous_barcodes);
    $previous_barcodes = array_values(array_unique(array_filter($previous_barcodes, 'is_string')));
    if ( empty($previous_barcodes) ) {
      return true;
    }
    $previous = implode(',', array_fill(0, count($previous_barcodes), '%s'));
    $current = implode(',', array_fill(0, count($barcodes), '%s'));
    return $this->change_links(
      "DELETE old_link, orphan_state FROM {$this->links_table} old_link
      LEFT JOIN {$this->links_table} other_link ON other_link.barcode = old_link.barcode AND other_link.order_id <> old_link.order_id
      LEFT JOIN {$this->states_table} orphan_state ON orphan_state.barcode = old_link.barcode AND other_link.order_id IS NULL
      WHERE old_link.order_id = %d AND old_link.barcode IN ($previous) AND old_link.barcode NOT IN ($current) AND",
      array_merge(array($order_id), $previous_barcodes, array_values($barcodes))
    );
  }

  public function order_barcodes( $order_id )
  {
    if ( ! $this->valid_order($order_id) ) {
      return false;
    }
    $barcodes = $this->db->get_col($this->db->prepare(
      "SELECT barcode FROM {$this->links_table} WHERE order_id = %d",
      $order_id
    ));
    return '' === $this->db->last_error && is_array($barcodes) ? $barcodes : false;
  }

  /** Remove an order link and remove shipment states only when no order uses them. */
  public function delete_order( $order_id )
  {
    if ( ! $this->valid_order($order_id) ) {
      return false;
    }
    return $this->change_links(
      "DELETE old_link, orphan_state FROM {$this->links_table} old_link
      LEFT JOIN {$this->links_table} other_link ON other_link.barcode = old_link.barcode AND other_link.order_id <> old_link.order_id
      LEFT JOIN {$this->states_table} orphan_state ON orphan_state.barcode = old_link.barcode AND other_link.order_id IS NULL
      WHERE old_link.order_id = %d AND",
      array($order_id)
    );
  }

  /** Null is absent; false is an error; an existing row includes its revision. */
  public function read( $account, $barcode )
  {
    $barcode = self::normalize_barcode($barcode);
    if ( ! $this->valid_account($account) || false === $barcode ) {
      return false;
    }
    $row = $this->db->get_row($this->db->prepare(
      "SELECT state, revision FROM {$this->states_table} WHERE account = %s AND barcode = %s",
      $account, $barcode
    ), ARRAY_A);
    if ( '' !== $this->db->last_error ) {
      return false;
    }
    if ( null === $row ) {
      return null;
    }
    $state = json_decode($row['state'], true);
    if ( ! is_array($state) || JSON_ERROR_NONE !== json_last_error() ) {
      return false;
    }
    return array('state' => $state, 'revision' => (string) $row['revision']);
  }

  /** Display reads can fall back to an earlier account after a credential change. */
  public function read_for_display( $account, $barcode )
  {
    $row = $this->read($account, $barcode);
    return null === $row ? $this->previous_state($barcode) : $row;
  }

  public function read_display_batch( $account, $barcodes )
  {
    if ( ! $this->valid_account($account) ) {
      return array();
    }
    $requested = array();
    foreach ( $barcodes as $barcode ) {
      $key = self::normalize_barcode($barcode);
      if ( false !== $key ) {
        $requested[$key][$barcode] = true;
      }
    }
    $result = array();
    foreach ( array_chunk(array_keys($requested), 100) as $batch ) {
      $placeholders = implode(',', array_fill(0, count($batch), '%s'));
      $rows = $this->db->get_results($this->db->prepare(
        "SELECT barcode, state FROM {$this->states_table} WHERE account = %s AND barcode IN ($placeholders)",
        array_merge(array($account), $batch)
      ), ARRAY_A);
      if ( '' !== $this->db->last_error || ! is_array($rows) ) {
        continue;
      }
      $active = array();
      foreach ( $rows as $row ) {
        $active[$row['barcode']] = json_decode($row['state'], true);
      }
      $missing = array();
      foreach ( $batch as $barcode ) {
        if ( array_key_exists($barcode, $active) ) {
          $state = is_array($active[$barcode]) ? array('state' => $active[$barcode]) : null;
          $result += array_fill_keys(array_keys($requested[$barcode]), $state);
        } else {
          $missing[] = $barcode;
        }
      }
      if ( empty($missing) ) {
        continue;
      }
      $placeholders = implode(',', array_fill(0, count($missing), '%s'));
      $rows = $this->db->get_results($this->db->prepare(
        "SELECT barcode, state FROM {$this->states_table} WHERE barcode IN ($placeholders) ORDER BY account",
        $missing
      ), ARRAY_A);
      // The second database query can change last_error independently of the first.
      // @phpstan-ignore notIdentical.alwaysFalse
      if ( '' !== $this->db->last_error || ! is_array($rows) ) {
        continue;
      }
      $prior = array();
      foreach ( $rows as $row ) {
        $prior[$row['barcode']][] = $row['state'];
      }
      foreach ( $missing as $barcode ) {
        $state = self::select_previous_state($prior[$barcode] ?? array());
        $result += array_fill_keys(array_keys($requested[$barcode]), $state);
      }
    }
    return $result;
  }

  public static function latest_event( $state )
  {
    $events = $state['events'] ?? array();
    if ( is_array($events) && ! empty($events) ) {
      $event = end($events);
      return is_array($event) ? $event : array();
    }
    return isset($state['event_id']) ? $state : array();
  }

  public function previous_state( $barcode )
  {
    $barcode = self::normalize_barcode($barcode);
    if ( false === $barcode ) { return null; }
    $rows = $this->db->get_col($this->db->prepare("SELECT state FROM {$this->states_table} WHERE barcode = %s ORDER BY account", $barcode));
    if ( '' !== $this->db->last_error || ! is_array($rows) ) { return false; }
    return self::select_previous_state($rows);
  }

  private static function select_previous_state( $rows )
  {
    $latest = null;
    $previous_id = '0';
    foreach ( $rows as $json ) {
      $state = json_decode($json, true);
      if ( ! is_array($state) ) { continue; }
      $event = self::latest_event($state);
      $id = OmnivaLt_Tracking_Sync::event_id($event['event_id'] ?? '0');
      if ( false !== $id && (null === $latest || OmnivaLt_Tracking_Sync::compare_ids($id, $previous_id) > 0) ) {
        $latest = $state;
        $previous_id = $id;
      }
    }
    return null === $latest ? null : array('state' => $latest);
  }

  /** True is saved, null means the revision or link changed, false is a write error. */
  public function write_result( $account, $barcode, $state, $revision )
  {
    $barcode = self::normalize_barcode($barcode);
    if ( ! $this->valid_account($account) || false === $barcode || ! is_array($state) ) {
      return false;
    }
    $encoded = wp_json_encode($state);
    if ( ! is_string($encoded) ) {
      return false;
    }
    if ( null !== $revision && (! is_string($revision) || ! preg_match('/^[0-9]+$/D', $revision)) ) {
      return false;
    }
    return $this->with_lock($this->link_state_lock, 1, function() use ($account, $barcode, $encoded, $revision) {
      $linked = $this->lock_condition() . " AND EXISTS (SELECT 1 FROM {$this->links_table} WHERE barcode = %s)";
      if ( null === $revision ) {
        $query = $this->db->prepare(
          "INSERT IGNORE INTO {$this->states_table} (account, barcode, state, revision)
          SELECT %s, %s, %s, 1 FROM DUAL WHERE {$linked}",
          $account, $barcode, $encoded, $barcode
        );
      } else {
        $query = $this->db->prepare(
          "UPDATE {$this->states_table} SET state = %s, revision = revision + 1
          WHERE account = %s AND barcode = %s AND revision = %s AND {$linked}",
          $encoded, $account, $barcode, $revision, $barcode
        );
      }
      $updated = $this->write_tracking_json($query);
      return 0 === $updated && $this->owns_current_locks() ? null : 1 === $updated;
    });
  }

  private function change_links( $sql, $args, $suffix = '' )
  {
    return $this->with_lock($this->link_state_lock, 1, function() use ($sql, $args, $suffix) {
      $changed = $this->db->query($this->db->prepare($sql . ' ' . $this->lock_condition() . $suffix, $args));
      return false !== $changed && (0 !== $changed || $this->owns_current_locks());
    });
  }

  /** Coordinate tracking operations on the shared wpdb connection. */
  public function with_lock( $scope, $timeout, $operation, &$acquired = null )
  {
    $lock = $this->supports_multiple_locks() ? $scope : $this->link_state_lock;
    $connection = spl_object_id($this->db);
    if ( isset(self::$held_locks[$connection][$lock]) ) {
      $acquired = $this->owns_current_locks() ? '1' : null;
      return '1' === $acquired ? $operation() : false;
    }
    $acquired = $this->db->get_var($this->db->prepare('SELECT GET_LOCK(%s, %d)', $lock, $timeout));
    if ( '1' !== (string) $acquired ) {
      return false;
    }
    self::$held_locks[$connection][$lock] = true;
    try {
      return $operation();
    } finally {
      unset(self::$held_locks[$connection][$lock]);
      $this->db->get_var($this->db->prepare('SELECT RELEASE_LOCK(%s)', $lock));
    }
  }

  private function supports_multiple_locks()
  {
    if ( null === $this->multiple_locks ) {
      $server = $this->db->db_server_info();
      $maria = false !== stripos($server, 'MariaDB');
      // MariaDB can report a MySQL compatibility prefix before its actual version.
      if ( $maria && 0 === strpos($server, '5.5.5-') ) {
        $server = substr($server, 6);
      }
      preg_match('/^\d+\.\d+\.\d+/', $server, $version);
      $this->multiple_locks = isset($version[0]) && version_compare($version[0], $maria ? '10.0.2' : '5.7.5', '>=');
    }
    return $this->multiple_locks;
  }

  private function lock_condition()
  {
    $locks = array_keys(self::$held_locks[spl_object_id($this->db)] ?? array());
    $conditions = array_fill(0, count($locks), 'IS_USED_LOCK(%s) = CONNECTION_ID()');
    return $this->db->prepare(implode(' AND ', $conditions), $locks);
  }

  private function owns_current_locks()
  {
    // wpdb can reconnect and retry on a connection that no longer holds the lock.
    return '1' === (string) $this->db->get_var('SELECT ' . $this->lock_condition());
  }

  private function write_tracking_json( $query )
  {
    // wpdb can log the full SQL, including tracking JSON, on query failure.
    $previous = $this->db->suppress_errors(true);
    try {
      return $this->db->query($query);
    } finally {
      $this->db->suppress_errors($previous);
    }
  }

  private function valid_order( $order_id )
  {
    return is_int($order_id) && $order_id > 0;
  }

  private function valid_account( $account )
  {
    return is_string($account) && 1 === preg_match('/^[a-f0-9]{64}$/D', $account);
  }

  public static function valid_barcode( $barcode )
  {
    return is_string($barcode) && 1 === preg_match('/^[A-Za-z0-9-]{1,64}$/D', $barcode);
  }

  /** Return the uppercase ASCII storage key, or false for invalid input. */
  public static function normalize_barcode( $barcode )
  {
    return self::valid_barcode($barcode) ? strtr($barcode, 'abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ') : false;
  }
}
