<?php
class OmnivaLt_Admin_Whats_New
{
  private const SEEN_OPTION = 'omnivalt_whats_new_seen_version';

  /** @phpstan-impure */
  private static function is_pending()
  {
    $seen_version = get_option(self::SEEN_OPTION, '');

    return ! is_string($seen_version) || '' === $seen_version
      || version_compare($seen_version, OMNIVALT_WHATS_NEW_VERSION, '<');
  }

  private static function nonce_action()
  {
    return 'omnivalt_dismiss_whats_new_' . OMNIVALT_WHATS_NEW_VERSION;
  }

  public static function get_script_data()
  {
    return array(
      'url' => admin_url('admin-ajax.php'),
      'loadNonce' => wp_create_nonce('omnivalt_load_whats_new'),
      'autoOpen' => self::is_pending() ? '1' : '0',
      'loadError' => __('Updates could not be loaded. Please try again.', 'omnivalt'),
      'changelogLoading' => __('Loading changelog…', 'omnivalt'),
      'changelogError' => __('The changelog could not be loaded. Please try again.', 'omnivalt'),
    );
  }

  private static function get_changelog_html()
  {
    // The plugin bootstrap defines this path via plugin_dir_path(), which PHPStan cannot resolve here.
    // @phpstan-ignore constant.notFound
    $changelog_path = OMNIVALT_DIR . 'changelog.txt';
    if ( ! is_readable($changelog_path) ) {
      return false;
    }

    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Read the plugin's local changelog.
    $changelog = file_get_contents($changelog_path);
    if ( ! is_string($changelog) ) {
      return false;
    }

    $lines = preg_split('/\r\n|\r|\n/', $changelog);
    if ( ! is_array($lines) ) {
      return false;
    }

    $html = '';
    $in_list = false;
    foreach ( $lines as $line ) {
      $line = trim((string) $line);

      if ( '' === $line ) {
        if ( $in_list ) {
          $html .= '</ul>';
          $in_list = false;
        }
        continue;
      }

      if ( preg_match('/^==\s*.+?\s*==$/', $line) ) {
        if ( $in_list ) {
          $html .= '</ul>';
          $in_list = false;
        }
        continue;
      }

      if ( preg_match('/^=\s*(.+?)\s*=$/', $line, $matches) ) {
        if ( $in_list ) {
          $html .= '</ul>';
          $in_list = false;
        }
        $html .= '<h3>' . esc_html($matches[1]) . '</h3>';
        continue;
      }

      if ( preg_match('/^[-*+]\s+(.+)$/', $line, $matches) ) {
        if ( ! $in_list ) {
          $html .= '<ul>';
          $in_list = true;
        }
        $html .= '<li>' . esc_html(ucfirst($matches[1])) . '</li>';
        continue;
      }

      if ( $in_list ) {
        $html .= '</ul>';
        $in_list = false;
      }
      $html .= '<p>' . esc_html($line) . '</p>';
    }

    if ( $in_list ) {
      $html .= '</ul>';
    }

    return $html;
  }

  public static function load()
  {
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- The server method must match GET exactly.
    if ( 'GET' !== ($_SERVER['REQUEST_METHOD'] ?? '') || ! current_user_can('manage_woocommerce') ) {
      wp_send_json_error(null, 403);
    }

    $nonce = isset($_GET['nonce']) && is_string($_GET['nonce']) ? sanitize_text_field(wp_unslash($_GET['nonce'])) : '';
    if ( ! wp_verify_nonce($nonce, 'omnivalt_load_whats_new') ) {
      wp_send_json_error(null, 403);
    }

    $mode = isset($_GET['mode']) && is_string($_GET['mode']) ? sanitize_key(wp_unslash($_GET['mode'])) : '';
    if ( ! in_array($mode, array('auto', 'manual', 'changelog'), true) ) {
      wp_send_json_error(null, 400);
    }

    if ( 'changelog' === $mode ) {
      $html = self::get_changelog_html();
      if ( false === $html ) {
        wp_send_json_error(null, 500);
      }

      wp_send_json_success(array('html' => $html));
    }

    $pending = self::is_pending();
    if ( 'auto' === $mode && ! $pending ) {
      wp_send_json_success(array('html' => ''));
    }

    ob_start();
    // The plugin bootstrap defines the path via plugin_dir_path(), which PHPStan cannot resolve in the staged scan.
    // @phpstan-ignore constant.notFound
    include OMNIVALT_DIR . 'templates/admin/settings-news.php';
    $html = ob_get_clean();

    wp_send_json_success(array(
      'html' => $html,
      'dismissNonce' => $pending ? wp_create_nonce(self::nonce_action()) : '',
    ));
  }

  public static function dismiss()
  {
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- The server method must match POST exactly.
    if ( 'POST' !== ($_SERVER['REQUEST_METHOD'] ?? '') || ! current_user_can('manage_woocommerce') ) {
      wp_send_json_error(null, 403);
    }

    $nonce = isset($_POST['nonce']) && is_string($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if ( ! wp_verify_nonce($nonce, self::nonce_action()) ) {
      wp_send_json_error(null, 403);
    }

    if ( self::is_pending() ) {
      // A concurrent dismissal can make update_option() return false without a write failure.
      if ( ! update_option(self::SEEN_OPTION, OMNIVALT_WHATS_NEW_VERSION, false) && self::is_pending() ) {
        wp_send_json_error(null, 500);
      }
    }

    wp_send_json_success();
  }
}
