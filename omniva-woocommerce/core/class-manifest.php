<?php
class OmnivaLt_Manifest
{
  const PAGE_SLUG = 'omniva-manifest';

  public static function load_admin_scripts()
  {
    OmnivaLt_Manifest_Page::load_admin_scripts();
  }

  public static function register_menu_pages()
  {
    OmnivaLt_Admin_Navigation::register_menu_pages();
  }

  public static function manifest_page()
  {
    OmnivaLt_Manifest_Page::render_page();
  }

  /**
   * Handle a custom query variable to get orders.
   * @param array $query - Args for WP_Query.
   * @param array $query_vars - Query vars from WC_Order_Query.
   * @return array modified $query
   */
  public static function handle_custom_query_var( $query, $query_vars )
  {
    if ( ! is_admin() ) {
      return $query;
    }
    
    $query['meta_query'] = self::build_meta_query($query_vars);

    return $query;
  }

  public static function build_meta_query( $args ) {
    $configs_meta = OmnivaLt_Core::get_configs('meta_keys');
    $meta_query = array(
      'relation' => 'AND'
    );

    if ( ! empty( $args[$configs_meta['method']] ) ) {
      $meta_query[] = array(
        'key' => $configs_meta['method'],
        'value' => $args[$configs_meta['method']],
        'compare' => 'IN',
      );
    }

    if ( isset( $args[$configs_meta['barcodes']] ) ) {
      if ( ! $args[$configs_meta['barcodes']] ) {
        $meta_query[] = array(
          'relation' => 'OR',
          array(
            'key' => $configs_meta['barcodes'],
            'compare' => 'NOT EXISTS',
          ),
          array(
            'key' => $configs_meta['barcodes'],
            'compare' => '=',
            'value' => '',
          )
        );
      } else if ( $args[$configs_meta['barcodes']] === true ) {
        $meta_query[] = array(
          'key' => $configs_meta['barcodes'],
          'compare' => 'EXISTS',
        );
        $meta_query[] = array(
          'key' => $configs_meta['barcodes'],
          'compare' => '!=',
          'value' => '',
        );
      } else {
        $meta_query[] = array(
          'key' => $configs_meta['barcodes'],
          'value' => $args[$configs_meta['barcodes']],
          'compare' => 'LIKE',
        );
      }
    }

    if ( isset( $args['omnivalt_customer'] ) ) {
      $meta_query[] = array(
          'relation' => 'OR',
          array(
            'key' => '_billing_first_name',
            'value' => $args['omnivalt_customer'],
            'compare' => 'LIKE'
          ),
          array(
            'key' => '_billing_last_name',
            'value' => $args['omnivalt_customer'],
            'compare' => 'LIKE'
          ),
          array(
            'key' => '_billing_company',
            'value' => $args['omnivalt_customer'],
            'compare' => 'LIKE'
          ),
          array(
            'key' => '_shipping_first_name',
            'value' => $args['omnivalt_customer'],
            'compare' => 'LIKE'
          ),
          array(
            'key' => '_shipping_last_name',
            'value' => $args['omnivalt_customer'],
            'compare' => 'LIKE'
          ),
          array(
            'key' => '_shipping_company',
            'value' => $args['omnivalt_customer'],
            'compare' => 'LIKE'
          )
      );
    }

    if ( isset( $args[$configs_meta['manifest_date']] ) ) {
      $value = $args[$configs_meta['manifest_date']];
      if ( ! $value ) {
        $meta_query[] = array(
          'relation' => 'OR',
          array(
            'key' => $configs_meta['manifest_date'],
            'compare' => 'NOT EXISTS',
          ),
          array(
            'key' => $configs_meta['manifest_date'],
            'compare' => '=',
            'value' => '',
          )
        );
      } else {
        $meta_query[] = array(
          'key' => $configs_meta['manifest_date'],
          'compare' => 'EXISTS',
        );
        $meta_query[] = array(
          'key' => $configs_meta['manifest_date'],
          'compare' => '!=',
          'value' => '',
        );
        if ( is_array($value) && $value[0] && $value[1] ) {
          $meta_query[] = array(
            'key' => $configs_meta['manifest_date'],
            'value' => $value,
            'compare' => 'BETWEEN'
          );
        } else if ( is_array($value) && $value[0] && ! $value[1] ) {
          $meta_query[] = array(
            'key' => $configs_meta['manifest_date'],
            'value' => $value[0],
            'compare' => '>='
          );
        } else if ( is_array($value) && ! $value[0] && $value[1] ) {
          $meta_query[] = array(
            'key' => $configs_meta['manifest_date'],
            'value' => $value[1],
            'compare' => '<='
          );
        }
      }
    }

    if ( isset( $args['omnivalt_original'] ) ) {
      $meta_query[] = array(
        'key'     => '_parent_id',
        'compare' => 'NOT EXISTS',
      );
    }

    return $meta_query;
  }

  public static function page_params()
  {
    return array(
      'strings' => array(
        'all_orders' => __('All orders', 'omnivalt'),
        'new_orders' => __('New orders', 'omnivalt'),
        'registered_orders' => __('Registered orders', 'omnivalt'),
        'manifest_orders' => __('Orders ready to ship', 'omnivalt'),
        'completed_orders' => __('Completed orders', 'omnivalt'),
      ),
      'filter_keys' => array('customer', 'status', 'shipment_status', 'barcode', 'id', 'start_date', 'end_date'),
      'per_page' => OmnivaLt_Filters::orders_list_per_page()
    );
  }

  public static function is_mannifest_orders_table( $tab_key )
  {
    $manifest_table_tabs = array('manifest_orders', 'completed_orders');
    return (in_array($tab_key, $manifest_table_tabs));
  }

  public static function page_make_link($args)
  {
    $query_args = array('page' => self::PAGE_SLUG);
    $query_args = array_merge($query_args, $args);
    return add_query_arg($query_args, admin_url('/admin.php'));
  }

  public static function page_get_orders()
  {
    global $wpdb;
    $configs = OmnivaLt_Core::get_configs();
    $page_params = self::page_params();

    $per_page = (isset($_GET['perpage'])) ? filter_input(INPUT_GET, 'perpage') : $page_params['per_page'];
    $paged = (isset($_GET['paged'])) ? filter_input(INPUT_GET, 'paged') : 1;
    $action = (isset($_GET['action'])) ? filter_input(INPUT_GET, 'action') : 'all_orders';

    $filters = array();
    foreach ( array_diff($page_params['filter_keys'], array('start_date', 'end_date')) as $filter_key ) {
      if (isset($_POST['filter_' . $filter_key]) && intval($_POST['filter_' . $filter_key]) !== -1) {
        $filters[$filter_key] = filter_input(INPUT_POST, 'filter_' . $filter_key);
      } else {
        $filters[$filter_key] = false;
      }
    }
    $labels = OmnivaLt_Manifest_Page::tracking_labels();
    $filters['shipment_status'] = is_string($filters['shipment_status']) && isset($labels[$filters['shipment_status']])
      ? $filters['shipment_status'] : false;
    $date_errors = array();
    foreach ( array('start_date', 'end_date') as $key ) {
      $date = filter_input(INPUT_POST, 'filter_' . $key);
      $filters[$key] = is_string($date) ? $date : '';
      if ( false === $date || (is_string($date) && '' !== $date
        && (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $date, $parts)
          || ! checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]))) ) {
        $date_errors[$key] = true;
      }
    }
    if ( empty($date_errors) && $filters['start_date'] && $filters['end_date'] && $filters['start_date'] > $filters['end_date'] ) {
      list($filters['start_date'], $filters['end_date']) = array($filters['end_date'], $filters['start_date']);
    }

    $shipping_methods = array_values(OmnivaLt_Method::get_all_wc_methods_keys());
    $shipping_methods[] = 'omnivalt';

    $args = array(
      'paginate' => true,
      'limit' => $per_page,
      'paged' => $paged,
      $configs['meta_keys']['method'] => $shipping_methods,
      'omnivalt_original' => true // Return only original Orders
    );

    // Handle query variables depending on selected tab
    switch ( $action ) {
      case 'new_orders':
        $page_title = $page_params['strings'][$action];
        $args['status'] = array('wc-processing', 'wc-on-hold', 'wc-pending');
        $args[$configs['meta_keys']['manifest_date']] = false;
        $args[$configs['meta_keys']['barcodes']] = false;
        break;
      case 'registered_orders':
        $page_title = $page_params['strings'][$action];
        $args['status'] = array('wc-processing', 'wc-on-hold', 'wc-pending');
        $args[$configs['meta_keys']['manifest_date']] = false;
        $args[$configs['meta_keys']['barcodes']] = true;
        break;
      case 'manifest_orders':
        $page_title = $page_params['strings'][$action];
        $args['status'] = array('wc-processing', 'wc-on-hold', 'wc-pending');
        $args[$configs['meta_keys']['manifest_date']] = true;
        $args['orderby'] = 'meta_value';
        $args['meta_key'] = $configs['meta_keys']['manifest_date'];
        $args['meta_type'] = 'DATETIME';
        $args['order'] = 'DESC';
        break;
      case 'completed_orders':
        $page_title = $page_params['strings'][$action];
        $args['status'] = array('wc-completed', 'wc-cancelled', 'wc-refunded', 'wc-failed');
        $args[$configs['meta_keys']['manifest_date']] = true;
        $args['orderby'] = 'meta_value';
        $args['order'] = 'DESC';
        break;
      case 'all_orders':
      default:
        $action = 'all_orders';
        $page_title = $page_params['strings']['all_orders'];
        break;
    }

    foreach ( $filters as $key => $filter ) {
      if ( $filter ) {
        switch ($key) {
          case 'status':
            $args['status'] = $filter;
            break;
          case 'barcode':
            $args[$configs['meta_keys']['barcodes']] = $filter;
            break;
          case 'customer':
            if ( OmnivaLt_Wc::is_using_hpos() ) {
              $args['field_query'][] = array(
                'relation' => 'OR',
                array(
                  'field' => 'billing_first_name',
                  'value' => $filter,
                  'compare' => 'LIKE'
                ),
                array(
                  'field' => 'billing_last_name',
                  'value' => $filter,
                  'compare' => 'LIKE'
                ),
                array(
                  'field' => 'shipping_first_name',
                  'value' => $filter,
                  'compare' => 'LIKE'
                ),
                array(
                  'field' => 'shipping_last_name',
                  'value' => $filter,
                  'compare' => 'LIKE'
                )
              );
            } else {
              $args['omnivalt_customer'] = $filter;
            }
            break;
        }
      }
    }
    // Date filter is a special case
    if ( empty($date_errors) && ($filters['start_date'] || $filters['end_date']) ) {
      $args[$configs['meta_keys']['manifest_date']] = array(
        $filters['start_date'],
        $filters['end_date'] ? $filters['end_date'] . ' 23:59:59' : false
      );
    }

    // Add meta_query if system using HPOS
    if ( OmnivaLt_Wc::is_using_hpos() ) {
      $args['meta_query'] = self::build_meta_query($args);
    }

    // Searching by ID takes priority
    $orders = array();
    $shipment_status_error = false;
    $single_order = false;
    if ( empty($date_errors) && $filters['id'] ) {
      $single_order = OmnivaLt_Wc_Order::get_order($filters['id']);
      $shipment_status_error = $filters['shipment_status'] && '' !== $wpdb->last_error;
      if ( $single_order && ! $filters['shipment_status'] ) {
        $orders = array($single_order); // Table printer expects array
        $paged = 1;
      }
    }

    // If there is no search by ID use to custom query
    $results = false;
    if ( empty($date_errors) && ! $shipment_status_error && (! $single_order || $filters['shipment_status']) ) {
      if ( $filters['shipment_status'] ) {
        $matching_ids = self::shipment_status_order_ids(
          $filters['shipment_status'], $filters['id'], $single_order, $configs['meta_keys']['barcodes']
        );
        $shipment_status_error = false === $matching_ids;
        $args['post__in'] = $matching_ids;
        $single_order = false;
      }
      if ( empty($filters['shipment_status']) || ! empty($args['post__in']) ) {
        $results = $filters['shipment_status']
          ? self::query_shipment_status_orders($args)
          : OmnivaLt_Wc_Order::get_orders($args, true);
        if ( $filters['shipment_status'] && ('' !== $wpdb->last_error
          || ! isset($results->orders, $results->total, $results->max_num_pages)
          || ! is_array($results->orders) || $results->total < count($results->orders)) ) {
          $shipment_status_error = true;
          $results = false;
        } elseif ( $results ) {
          $orders = $results->orders;
        }
      }
    }

    $there_is_orders = ! $shipment_status_error && ($single_order || ($results && $results->total > 0));

    // Make pagination
    $page_links = false;
    if ($results) {
      $page_links = paginate_links(array(
        'base' => add_query_arg('paged', '%#%'),
        'format' => '?paged=%#%',
        'prev_text' => __('&laquo;', 'text-domain'),
        'next_text' => __('&raquo;', 'text-domain'),
        'total' => $results->max_num_pages,
        'current' => $paged,
        'type' => 'plain'
      ));
    }

    $order_statuses = OmnivaLt_Wc_Order::get_all_statuses();

    return array(
      'orders' => $orders,
      'statuses' => $order_statuses,
      'paged' => $paged,
      'action' => $action,
      'links' => $page_links,
      'is_orders' => $there_is_orders,
      'filters' => $filters,
      'date_errors' => $date_errors,
      'shipment_status_error' => $shipment_status_error,
    );
  }

  private static function shipment_status_order_ids( $status, $requested_id, $order, $barcode_meta_key )
  {
    global $wpdb;
    $account = OmnivaLt_Tracking_Sync::account();
    if ( ! $account || ($requested_id && ! $order) ) {
      return array();
    }
    $storage = OmnivaLt_Tracking_Sync::storage();
    if ( $order ) {
      $barcodes = (array) $order->get_meta($barcode_meta_key, true);
      if ( '' !== $wpdb->last_error ) {
        return false;
      }
      if ( empty($barcodes) ) {
        return array();
      }
      $states = $storage->read_display_batch($account['id'], $barcodes, true);
      if ( false === $states ) {
        return false;
      }
      foreach ( $states as $row ) {
        if ( $status === ($row['state']['status'] ?? null) ) {
          return array($order->get_id());
        }
      }
      return array();
    }
    $candidates = $storage->order_barcodes_for_status($account['id'], $status);
    return false === $candidates ? false : self::orders_with_current_barcodes($candidates, $barcode_meta_key);
  }

  private static function orders_with_current_barcodes( $candidates, $barcode_meta_key )
  {
    global $wpdb;
    $matching_ids = array();
    foreach ( array_chunk(array_keys($candidates), 100) as $order_ids ) {
      $orders = self::query_shipment_status_orders(array(
        'post__in' => $order_ids,
        'limit' => count($order_ids),
        'paginate' => false,
        'return' => 'objects',
      ));
      if ( '' !== $wpdb->last_error || ! is_array($orders) ) {
        return false;
      }
      foreach ( $orders as $order ) {
        if ( ! $order instanceof WC_Order ) {
          continue;
        }
        $id = $order->get_id();
        // Use current order metadata, as the rendered shipment row does.
        $barcodes = (array) $order->get_meta($barcode_meta_key, true);
        // Reading order metadata can issue another database query.
        // @phpstan-ignore notIdentical.alwaysFalse
        if ( '' !== $wpdb->last_error ) {
          return false;
        }
        foreach ( $barcodes as $barcode ) {
          $key = OmnivaLt_Tracking_Storage::normalize_barcode($barcode);
          if ( false !== $key && isset($candidates[$id][$key]) ) {
            $matching_ids[] = $id;
            break;
          }
        }
      }
    }
    return $matching_ids;
  }

  private static function query_shipment_status_orders( $args )
  {
    try {
      return wc_get_orders($args);
    } catch ( \Exception $e ) {
      // HPOS can throw if an order disappears between selecting IDs and loading objects.
      return false;
    }
  }
}
