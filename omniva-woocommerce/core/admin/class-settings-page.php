<?php
class OmnivaLt_Settings_Page
{
  const PAGE_SLUG = 'omnivalt-settings';

  public static function register_menu_page()
  {
    add_submenu_page(
      'woocommerce',
      __('Omniva settings', 'omnivalt'),
      __('Omniva settings', 'omnivalt'),
      'manage_woocommerce',
      self::PAGE_SLUG,
      array('OmnivaLt_Settings_Page', 'render_page'),
      11
    );
  }

  public static function save_settings()
  {
    $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
    if ( self::PAGE_SLUG !== $page || ! isset($_POST['save']) ) {
      return;
    }

    if ( ! current_user_can('manage_woocommerce') ) {
      wp_die(esc_html__('You do not have permission to manage Omniva settings.', 'omnivalt'));
    }

    check_admin_referer('woocommerce-settings');

    $shipping_method = self::get_shipping_method();
    $shipping_method->init_form_fields();
    $shipping_method->process_admin_options();

    WC_Admin_Settings::add_message(__('Your settings have been saved.', 'omnivalt'));
  }

  public static function render_page()
  {
    if ( ! current_user_can('manage_woocommerce') ) {
      return;
    }

    $shipping_method = self::get_shipping_method();
    $shipping_method->init_form_fields();

    include OMNIVALT_DIR . 'templates/admin/settings-page.php';
  }

  public static function get_settings_layout()
  {
    return array(
      'tabs' => array(
        'general' => __('Setup', 'omnivalt'),
        'rules' => __('Delivery & checkout', 'omnivalt'),
        'workflow' => __('Order fulfilment', 'omnivalt'),
        'advanced' => __('Diagnostics', 'omnivalt'),
      ),
      'cards' => array(
        'general' => array(
          'type' => 'settings',
          'section' => 'general',
          'tab' => 'general',
        ),
        'api' => array(
          'type' => 'settings',
          'section' => 'api',
          'tab' => 'general',
        ),
        'shop' => array(
          'type' => 'settings',
          'section' => 'shop',
          'tab' => 'general',
          'fields' => array(
            'company',
            'shop_name',
            'shop_address',
            'shop_city',
            'shop_postcode',
            'shop_countrycode',
            'shop_phone',
            'shop_mobile',
            'shop_email',
            'bank_account',
            'pick_up_start',
            'pick_up_end',
            'send_off',
          ),
        ),
        'shipping_methods' => array(
          'type' => 'shipping_methods',
          'section' => 'methods',
          'prices_section' => 'prices',
          'tab' => 'rules',
        ),
        'settings' => array(
          'type' => 'settings',
          'section' => 'settings',
          'tab' => 'rules',
        ),
        'design' => array(
          'type' => 'settings',
          'section' => 'design',
          'tab' => 'advanced',
        ),
        'orders' => array(
          'type' => 'settings',
          'section' => 'orders',
          'tab' => 'workflow',
        ),
        'labels' => array(
          'type' => 'settings',
          'section' => 'labels',
          'tab' => 'workflow',
        ),
        'manifest' => array(
          'type' => 'settings',
          'section' => 'manifest',
          'tab' => 'workflow',
        ),
        'pickup' => array(
          'type' => 'settings',
          'section' => 'pickup',
          'tab' => 'workflow',
        ),
        'debug' => array(
          'type' => 'settings',
          'section' => 'debug',
          'tab' => 'advanced',
        ),
      ),
    );
  }

  public static function get_shipping_method_layout( $shipping_method )
  {
    $methods = array();

    foreach ( OmnivaLt_Method::get_all_shipping_methods() as $method ) {
      $field_key = 'method_' . $method['key'];
      if ( ! isset($shipping_method->form_fields[$field_key]) ) {
        continue;
      }

      $methods[] = array(
        'key' => $method['key'],
        'field_key' => $field_key,
        'title' => $method['title'],
        'description' => $method['description'],
      );
    }

    return $methods;
  }

  private static function get_shipping_method()
  {
    if ( ! class_exists('Omnivalt_Shipping_Method') ) {
      OmnivaLt_Core::init_shipping_method();
    }

    return new Omnivalt_Shipping_Method();
  }
}
