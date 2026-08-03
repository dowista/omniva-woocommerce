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

  private static function get_shipping_method()
  {
    if ( ! class_exists('Omnivalt_Shipping_Method') ) {
      OmnivaLt_Core::init_shipping_method();
    }

    return new Omnivalt_Shipping_Method();
  }
}
