<?php
if ( ! defined('ABSPATH') ) {
  exit;
}

// The plugin bootstrap defines the URL via plugin_dir_url(), which PHPStan cannot resolve in the staged scan.
// @phpstan-ignore constant.notFound
$news_image_url = OMNIVALT_URL . 'assets/img/admin/';
?>
<dialog id="omnivalt-settings-news" class="omnivalt-settings-news" aria-labelledby="omnivalt-settings-news-title">
  <div class="omnivalt-settings-news__layout">
    <form method="dialog" class="omnivalt-settings-news__dismiss">
      <button type="submit" class="omnivalt-settings-news__close" aria-label="<?php esc_attr_e('Close updates', 'omnivalt'); ?>" autofocus>
        <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
      </button>
    </form>

    <header class="omnivalt-settings-news__intro">
      <h2 id="omnivalt-settings-news-title"><?php esc_html_e("What's new?", 'omnivalt'); ?></h2>
      <p class="omnivalt-settings-news__version">
        <?php
        /* translators: %s: Installed plugin version. */
        echo esc_html(sprintf(__('Version %s', 'omnivalt'), OMNIVALT_VERSION));
        ?>
      </p>
      <div class="omnivalt-settings-news__brand-art" aria-hidden="true">
        <img class="omnivalt-settings-news__illustration" src="<?php echo esc_url($news_image_url . 'news-parcel-illustration.png'); ?>" width="240" height="343" alt="" loading="lazy" />
      </div>
    </header>

    <div class="omnivalt-settings-news__updates" role="region" aria-label="<?php esc_attr_e('Plugin updates', 'omnivalt'); ?>" tabindex="0">
      <section class="omnivalt-settings-news__update">
        <div class="omnivalt-settings-news__update-heading">
          <img class="omnivalt-settings-news__icon" src="<?php echo esc_url($news_image_url . 'news-tracking-icon.png'); ?>" width="128" height="128" alt="" loading="lazy" />
          <div>
            <h3><?php esc_html_e('Shipment tracking and event history', 'omnivalt'); ?></h3>
            <p><?php esc_html_e('The Shipments page shows the tracking status for each shipment. Hover over the status to see the latest event, or click it to open the full tracking history.', 'omnivalt'); ?></p>
          </div>
        </div>
        <img class="omnivalt-settings-news__preview omnivalt-settings-news__preview--framed" src="<?php echo esc_url($news_image_url . 'shipment-tracking-history.webp'); ?>" width="600" height="396" alt="<?php esc_attr_e('Hovering over a shipment status and opening its tracking history.', 'omnivalt'); ?>" loading="lazy" decoding="async" />
      </section>

      <section class="omnivalt-settings-news__update">
        <div class="omnivalt-settings-news__update-heading">
          <img class="omnivalt-settings-news__icon" src="<?php echo esc_url($news_image_url . 'news-sync-icon.png'); ?>" width="128" height="128" alt="" loading="lazy" />
          <div>
            <h3><?php esc_html_e('Automatic synchronization', 'omnivalt'); ?></h3>
            <p><?php esc_html_e('Shipment tracking data updates automatically in the background every hour. You can also refresh it manually once per minute.', 'omnivalt'); ?></p>
          </div>
        </div>
      </section>

      <section class="omnivalt-settings-news__update">
        <div class="omnivalt-settings-news__update-heading">
          <img class="omnivalt-settings-news__icon" src="<?php echo esc_url($news_image_url . 'news-filter-icon.png'); ?>" width="128" height="128" alt="" loading="lazy" />
          <div>
            <h3><?php esc_html_e('Status and date filters', 'omnivalt'); ?></h3>
            <p><?php esc_html_e('Filter shipments by the same tracking statuses shown in the shipment list. The date range includes the end date, and invalid dates are flagged.', 'omnivalt'); ?></p>
            <img class="omnivalt-settings-news__preview omnivalt-settings-news__preview--filter" src="<?php echo esc_url($news_image_url . 'shipment-status-filter.gif'); ?>" width="360" height="365" alt="<?php esc_attr_e('Selecting a tracking status in the shipment filter.', 'omnivalt'); ?>" loading="lazy" decoding="async" />
          </div>
        </div>
      </section>

      <button type="button" class="omnivalt-settings-news__changelog-open" data-settings-news-changelog-open>
        <span><?php esc_html_e('View changelog', 'omnivalt'); ?></span>
        <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
      </button>
    </div>

    <section class="omnivalt-settings-news__changelog" data-settings-news-changelog hidden role="region" aria-labelledby="omnivalt-settings-news-changelog-title" tabindex="0">
      <div class="omnivalt-settings-news__changelog-toolbar">
        <button type="button" class="omnivalt-settings-news__changelog-back" data-settings-news-changelog-back aria-label="<?php esc_attr_e('Back to updates', 'omnivalt'); ?>">
          <span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
          <span><?php esc_html_e('Back', 'omnivalt'); ?></span>
        </button>
        <h3 id="omnivalt-settings-news-changelog-title" tabindex="-1"><?php esc_html_e('Changelog', 'omnivalt'); ?></h3>
      </div>
      <div class="omnivalt-settings-news__changelog-content" data-settings-news-changelog-content>
        <p data-settings-news-changelog-status role="status"><?php esc_html_e('Loading changelog…', 'omnivalt'); ?></p>
      </div>
    </section>
  </div>
</dialog>
