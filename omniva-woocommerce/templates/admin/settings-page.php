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

$settings_layout = OmnivaLt_Settings_Page::get_settings_layout();
$shipping_method_layout = OmnivaLt_Settings_Page::get_shipping_method_layout($shipping_method);

$resolve_card_section = function( $card, $section_key ) use ( $settings_sections ) {
  $section = isset($settings_sections[$section_key]) ? $settings_sections[$section_key] : array(
    'title' => '',
    'description' => '',
    'fields' => array(),
  );
  $section['_sources'] = array();

  // A card without a field list owns its complete source section. A field list can pull
  // individual fields from any source section when the layout is reorganised later.
  if ( empty($card['fields']) ) {
    foreach ( $section['fields'] as $field_key => $field ) {
      $section['_sources'][] = array(
        'section' => $section_key,
        'key' => $field_key,
      );
    }

    return $section;
  }

  $section['fields'] = array();
  foreach ( $card['fields'] as $field_reference ) {
    $source_section_key = $section_key;
    $field_key = $field_reference;

    if ( is_array($field_reference) ) {
      $source_section_key = isset($field_reference['section']) ? $field_reference['section'] : $section_key;
      $field_key = isset($field_reference['key']) ? $field_reference['key'] : '';
    }

    if ( ! $field_key ) {
      continue;
    }

    if ( isset($settings_sections[$source_section_key]['fields'][$field_key]) ) {
      $section['fields'][$field_key] = $settings_sections[$source_section_key]['fields'][$field_key];
      $section['_sources'][] = array(
        'section' => $source_section_key,
        'key' => $field_key,
      );
      continue;
    }

    // A plain field key may be moved without repeating its current source section.
    foreach ( $settings_sections as $candidate_section_key => $candidate_section ) {
      if ( ! isset($candidate_section['fields'][$field_key]) ) {
        continue;
      }

      $section['fields'][$field_key] = $candidate_section['fields'][$field_key];
      $section['_sources'][] = array(
        'section' => $candidate_section_key,
        'key' => $field_key,
      );
      break;
    }
  }

  return $section;
};

$prepare_settings_rows = function( $rows ) {
  return preg_replace_callback(
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
    $rows
  );
};

$render_settings_card = function( $section_key, $section, $tab = '' ) use ( $shipping_method, $section_icons, $prepare_settings_rows ) {
  if ( empty($section['fields']) ) {
    return '';
  }

  $section_rows = $prepare_settings_rows($shipping_method->generate_settings_html($section['fields'], false));
  $section_rows = str_replace('class="description"', 'class="description omniva-field__desc"', $section_rows);
  $section_html = '<table class="form-table omniva-settings">';
  $section_html .= $section_rows;
  $section_html .= '</table>';
  $icon = isset($section_icons[$section_key]) ? $section_icons[$section_key] : 'admin-generic';

  ob_start();
  ?>
  <section class="omnivalt-settings-page__card" data-settings-card="<?php echo esc_attr($section_key); ?>"<?php echo $tab ? ' data-settings-tab="' . esc_attr($tab) . '"' : ''; ?>>
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
  <?php
  return ob_get_clean();
};
?>
<div id="omnivalt-settings-root" class="omnivalt-settings-page is-layout-pending">
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
      <?php $first_tab = true; ?>
      <?php foreach ( $settings_layout['tabs'] as $tab_key => $tab_title ) : ?>
        <button type="button" class="omniva-tabs__tab<?php echo $first_tab ? ' is-active' : ''; ?>" data-settings-tab="<?php echo esc_attr($tab_key); ?>"<?php echo $first_tab ? ' aria-current="page"' : ''; ?>><?php echo esc_html($tab_title); ?></button>
        <?php $first_tab = false; ?>
      <?php endforeach; ?>
    </nav>

    <main class="omnivalt-settings-page__main woocommerce">
      <?php WC_Admin_Settings::show_messages(); ?>

      <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=omnivalt-settings')); ?>">
        <?php wp_nonce_field('woocommerce-settings'); ?>

        <?php $rendered_sections = array(); ?>
        <?php $rendered_fields = array(); ?>
        <?php foreach ( $settings_layout['cards'] as $card_key => $card ) : ?>
          <?php
          $section_key = $card['section'];
          $has_field_list = isset($card['fields']) && is_array($card['fields']) && ! empty($card['fields']);
          if ( isset($rendered_sections[$section_key]) && ! $has_field_list ) {
            continue;
          }
          $section = $resolve_card_section($card, $section_key);
          foreach ( $section['_sources'] as $source ) {
            $rendered_fields[$source['section']][$source['key']] = true;
          }
          unset($section['_sources']);
          if ( ! $has_field_list ) {
            $rendered_sections[$section_key] = true;
          }
          ?>
          <?php if ( $card['type'] === 'shipping_methods' ) : ?>
            <?php
            $prices_section_key = $card['prices_section'];
            $prices_section = isset($settings_sections[$prices_section_key]) ? $settings_sections[$prices_section_key] : array();
            $rendered_sections[$prices_section_key] = true;
            $methods_icon = isset($section_icons[$section_key]) ? $section_icons[$section_key] : 'admin-generic';
            ?>
            <section class="omnivalt-settings-page__card omniva-shipping-methods-card" data-settings-card="<?php echo esc_attr($card_key); ?>" data-settings-tab="<?php echo esc_attr($card['tab']); ?>">
              <div class="omniva-title omnivalt-settings-page__card-header">
                <div class="title">
                  <div class="omnivalt-settings-page__card-header-top">
                    <span class="omnivalt-settings-page__card-header-icon dashicons dashicons-<?php echo esc_attr($methods_icon); ?>" aria-hidden="true"></span>
                    <h2><?php echo esc_html($section['title']); ?></h2>
                  </div>
                </div>
              </div>
              <div class="omnivalt-settings-page__card-body">
                <div class="omniva-shipping-methods-layout" data-omniva-shipping-layout>
                  <?php foreach ( $shipping_method_layout as $method ) : ?>
                    <?php
                    $method_field = array($method['field_key'] => $section['fields'][$method['field_key']]);
                    $method_html = $prepare_settings_rows($shipping_method->generate_settings_html($method_field, false));
                    ?>
                    <article class="omniva-shipping-method" data-omniva-method-group data-method-key="<?php echo esc_attr($method['key']); ?>">
                      <div class="omniva-shipping-method__control">
                        <table class="form-table omniva-settings">
                          <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Generated by WooCommerce's escaped settings field renderer. ?>
                          <?php echo $method_html; ?>
                        </table>
                      </div>
                      <div class="omniva-shipping-method__countries" data-method-countries>
                        <h3><?php esc_html_e('Delivery countries', 'omnivalt'); ?></h3>
                        <p><?php esc_html_e('Choose the countries where this shipping method should be available.', 'omnivalt'); ?></p>
                        <div class="omniva-shipping-method__country-list" data-method-country-list></div>
                      </div>
                    </article>
                  <?php endforeach; ?>

                  <article class="omniva-shipping-method omniva-shipping-method--international" data-omniva-method-group data-method-key="international">
                    <div class="omniva-shipping-method__control">
                      <h3><?php esc_html_e('International services', 'omnivalt'); ?></h3>
                      <p><?php esc_html_e('Configure international service package and region settings.', 'omnivalt'); ?></p>
                    </div>
                    <div class="omniva-shipping-method__countries" data-method-countries>
                      <div class="omniva-shipping-method__country-list" data-method-country-list></div>
                    </div>
                  </article>

                  <?php if ( isset($section['fields']['txt_returns']) ) : ?>
                    <div class="omniva-shipping-methods__returns">
                      <table class="form-table omniva-settings">
                        <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Generated by WooCommerce's escaped settings field renderer. ?>
                        <?php echo $prepare_settings_rows($shipping_method->generate_settings_html(array('txt_returns' => $section['fields']['txt_returns']), false)); ?>
                      </table>
                    </div>
                  <?php endif; ?>

                  <div class="omniva-delivery-prices-source" data-omniva-delivery-source aria-hidden="true">
                    <table class="form-table omniva-settings">
                      <?php if ( ! empty($prices_section['fields']) ) : ?>
                        <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Generated by WooCommerce's escaped settings field renderer. ?>
                        <?php echo $shipping_method->generate_settings_html($prices_section['fields'], false); ?>
                      <?php endif; ?>
                    </table>
                  </div>
                </div>
              </div>
            </section>
          <?php else : ?>
            <?php echo $render_settings_card($section_key, $section, $card['tab']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered settings card. ?>
          <?php endif; ?>
        <?php endforeach; ?>

        <?php foreach ( $settings_sections as $section_key => $section ) : ?>
          <?php
          if ( isset($rendered_sections[$section_key]) ) {
            continue;
          }
          $remaining_fields = array();
          foreach ( $section['fields'] as $field_key => $field ) {
            if ( empty($rendered_fields[$section_key][$field_key]) ) {
              $remaining_fields[$field_key] = $field;
            }
          }
          if ( empty($remaining_fields) ) {
            continue;
          }
          $section['fields'] = $remaining_fields;
          ?>
          <?php echo $render_settings_card($section_key, $section); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered fallback settings card. ?>
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
