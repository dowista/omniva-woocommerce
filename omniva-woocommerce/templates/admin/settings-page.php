<?php
if ( ! defined('ABSPATH') ) {
  exit;
}

$section_icons = array(
  'general' => 'admin-generic',
  'api' => 'admin-links',
  'shop' => 'store',
  'methods' => 'admin-site-alt3',
  'prices' => 'money-alt',
  'settings' => 'admin-settings',
  'design' => 'art',
  'orders' => 'cart',
  'labels' => 'media-document',
  'manifest' => 'media-spreadsheet',
  'pickup' => 'location-alt',
  'debug' => 'admin-tools',
);

$settings_sections = array(
  'general' => array(
    'title' => __('General', 'omnivalt'),
    'description' => __('Core Omniva shipping configuration.', 'omnivalt'),
    'fields' => array(),
  ),
);
$current_section = 'general';

// WooCommerce uses hr_* fields as section boundaries; keep the existing field definitions as the source of truth.
foreach ( $shipping_method->form_fields as $field_key => $field ) {
  if ( isset($field['type']) && $field['type'] === 'hr' ) {
    if ( empty($field['title']) ) {
      continue;
    }

    $current_section = str_replace('hr_', '', $field_key);
    $settings_sections[$current_section] = array(
      'title' => $field['title'],
      'description' => '',
      'fields' => array(),
    );
    continue;
  }

  $settings_sections[$current_section]['fields'][$field_key] = $field;
}
?>
<div id="omnivalt-settings-root" class="omnivalt-settings-page">
  <header class="omnivalt-settings-page__header">
    <div class="omnivalt-settings-page__header-content">
      <div>
        <div class="omnivalt-settings-page__breadcrumb">
          <span><?php esc_html_e('WooCommerce', 'omnivalt'); ?></span>
          <span class="omnivalt-settings-page__breadcrumb-sep" aria-hidden="true">/</span>
          <span><?php esc_html_e('Omniva', 'omnivalt'); ?></span>
        </div>
        <h1 class="omnivalt-settings-page__title"><?php esc_html_e('Omniva settings', 'omnivalt'); ?></h1>
        <p class="omnivalt-settings-page__subtitle"><?php esc_html_e('Configure Omniva shipping methods, delivery services and labels.', 'omnivalt'); ?></p>
      </div>

      <!-- <a class="omnivalt-settings-page__brand" href="https://www.omniva.lt" target="_blank" rel="noopener noreferrer" aria-label="Omniva.lt">
        <span class="omnivalt-settings-page__brand-logo" aria-hidden="true"></span>
        <span class="omnivalt-settings-page__brand-slogan"><?php esc_html_e('Pristatome', 'omnivalt'); ?><br /><?php esc_html_e('džiaugsmą', 'omnivalt'); ?></span>
      </a> -->
    </div>
  </header>

  <div class="omnivalt-settings-page__layout">
    <nav class="omnivalt-settings-page__tabs omniva-tabs" aria-label="<?php esc_attr_e('Omniva settings sections', 'omnivalt'); ?>">
      <button type="button" class="omniva-tabs__tab is-active" aria-current="page"><?php esc_html_e('General', 'omnivalt'); ?></button>
      <button type="button" class="omniva-tabs__tab"><?php esc_html_e('Rules & Exclusions', 'omnivalt'); ?></button>
      <button type="button" class="omniva-tabs__tab"><?php esc_html_e('Workflow', 'omnivalt'); ?></button>
      <button type="button" class="omniva-tabs__tab"><?php esc_html_e('Advanced', 'omnivalt'); ?></button>
    </nav>

    <main class="omnivalt-settings-page__main woocommerce">
      <?php WC_Admin_Settings::show_messages(); ?>

      <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=omnivalt-settings')); ?>">
        <?php wp_nonce_field('woocommerce-settings'); ?>

        <?php foreach ( $settings_sections as $section_key => $section ) : ?>
          <?php if ( empty($section['fields']) ) { continue; } ?>
          <?php
          $section_rows = $shipping_method->generate_settings_html($section['fields'], false);
          // Add presentation classes to generated rows without replacing WooCommerce's field renderer.
          $section_rows = preg_replace_callback(
            '/<tr(\s[^>]*)?>/',
            function( $matches ) {
              $attributes = isset($matches[1]) ? $matches[1] : '';

              if ( strpos($attributes, 'class=') !== false ) {
                $attributes = preg_replace('/class=(["\'])([^"\']*)\1/', 'class=$1$2 omniva-field$1', $attributes, 1);
              } else {
                $attributes .= ' class="omniva-field"';
              }

              return '<tr' . $attributes . '>';
            },
            $section_rows
          );
          $section_rows = str_replace('class="description"', 'class="description omniva-field__desc"', $section_rows);
          $section_html = '<table class="form-table omniva-settings">';
          $section_html .= $section_rows;
          $section_html .= '</table>';
          $icon = isset($section_icons[$section_key]) ? $section_icons[$section_key] : 'admin-generic';
          ?>
          <section class="omnivalt-settings-page__card">
            <div class="omniva-title omnivalt-settings-page__card-header">
              <div class="title">
                <div class="omnivalt-settings-page__card-header-top">
                  <span class="omnivalt-settings-page__card-header-icon dashicons dashicons-<?php echo esc_attr($icon); ?>" aria-hidden="true"></span>
                  <h2><?php echo esc_html($section['title']); ?></h2>
                </div>
                <?php if ( ! empty($section['description']) ) : ?>
                  <p class="omnivalt-settings-page__card-subtitle"><?php echo esc_html($section['description']); ?></p>
                <?php endif; ?>
              </div>
            </div>
            <div class="omnivalt-settings-page__card-body">
              <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Generated by WooCommerce's escaped settings field renderer. ?>
              <?php echo $section_html; ?>
            </div>
          </section>
        <?php endforeach; ?>

        <div class="omnivalt-settings-page__savebar">
          <button type="submit" name="save" class="woocommerce-save-button" value="<?php esc_attr_e('Save changes', 'omnivalt'); ?>">
            <?php esc_html_e('Save settings', 'omnivalt'); ?>
          </button>
        </div>
      </form>
    </main>

    <aside class="omnivalt-settings-page__visual" aria-hidden="true">
      <!-- <img class="omnivalt-settings-page__visual-logo" src="<?php echo esc_url(OMNIVALT_URL . 'assets/img/admin/logo-black.svg'); ?>" alt="" /> -->
      <img class="omnivalt-settings-page__visual-image" src="<?php echo esc_url(OMNIVALT_URL . 'assets/img/admin/settings.svg'); ?>" alt="" />
    </aside>
  </div>
</div>
