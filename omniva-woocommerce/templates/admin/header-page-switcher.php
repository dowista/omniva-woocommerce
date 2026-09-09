<?php
if ( ! defined('ABSPATH') ) {
  exit;
}

/**
 * @var array{title: string} $page
 * @var array<int, array{title: string, url: string}> $switcher_items
 * @var string $switcher_id
 * @var string $title_class
 */
?>
<div class="omnivalt-admin-page-switcher" data-omnivalt-page-switcher>
  <h1 class="<?php echo esc_attr($title_class); ?>">
    <?php if ( ! empty($switcher_items) ) : ?>
      <button
        type="button"
        class="omnivalt-admin-page-switcher__trigger"
        data-omnivalt-page-switcher-trigger
        aria-haspopup="true"
        aria-expanded="false"
        aria-controls="<?php echo esc_attr($switcher_id); ?>"
      >
        <span class="omnivalt-admin-page-switcher__title"><?php echo esc_html($page['title']); ?></span>
        <span class="omnivalt-admin-page-switcher__arrow dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
      </button>
    <?php else : ?>
      <?php echo esc_html($page['title']); ?>
    <?php endif; ?>
  </h1>

  <?php if ( ! empty($switcher_items) ) : ?>
    <nav
      id="<?php echo esc_attr($switcher_id); ?>"
      class="omnivalt-admin-page-switcher__menu"
      data-omnivalt-page-switcher-menu
      aria-label="<?php esc_attr_e('Omniva pages', 'omnivalt'); ?>"
      hidden
    >
      <ul class="omnivalt-admin-page-switcher__list">
        <?php foreach ( $switcher_items as $item ) : ?>
          <li>
            <a href="<?php echo esc_url($item['url']); ?>" data-omnivalt-page-link><?php echo esc_html($item['title']); ?></a>
          </li>
        <?php endforeach; ?>
      </ul>
    </nav>
  <?php endif; ?>
</div>
