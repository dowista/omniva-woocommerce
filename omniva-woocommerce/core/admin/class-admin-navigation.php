<?php
class OmnivaLt_Admin_Navigation
{
  const SETTINGS_PAGE_SLUG = 'omnivalt-settings';
  const MANIFEST_PAGE_SLUG = 'omniva-manifest';

  public static function get_pages()
  {
    return array(
      self::SETTINGS_PAGE_SLUG => array(
        'title' => __('Omniva settings', 'omnivalt'),
        'capability' => 'manage_woocommerce',
        'url' => add_query_arg(
          array('page' => self::SETTINGS_PAGE_SLUG),
          admin_url('admin.php')
        ),
      ),
      self::MANIFEST_PAGE_SLUG => array(
        'title' => __('Omniva shipping', 'omnivalt'),
        'capability' => 'manage_woocommerce',
        'url' => add_query_arg(
          array('page' => self::MANIFEST_PAGE_SLUG),
          admin_url('admin.php')
        ),
      ),
    );
  }

  public static function get_page( $slug )
  {
    $pages = self::get_pages();

    return isset($pages[$slug]) ? $pages[$slug] : false;
  }

  public static function get_switcher_items( $current_slug )
  {
    $items = array();

    foreach ( self::get_pages() as $slug => $page ) {
      if ( $slug === $current_slug || empty($page['capability']) || ! current_user_can($page['capability']) ) {
        continue;
      }

      $items[] = $page;
    }

    return $items;
  }

  public static function render_page_title( $current_slug, $title_class = '' )
  {
    $page = self::get_page($current_slug);
    if ( ! $page ) {
      return;
    }

    $switcher_items = self::get_switcher_items($current_slug);
    $title_class = sanitize_html_class($title_class);
    $switcher_id = 'omnivalt-page-switcher-' . sanitize_html_class($current_slug);

    // The plugin bootstrap defines OMNIVALT_DIR before this renderer can run.
    // @phpstan-ignore-next-line constant.notFound
    include OMNIVALT_DIR . 'templates/admin/header-page-switcher.php';
  }

}
