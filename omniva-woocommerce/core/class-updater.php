<?php
defined('OMNIVALT_VERSION') or die();

class OmnivaLt_Updater
{
  public static function get_latest_update()
  {
    $update_params = self::get_update_params();

    if ( empty($update_params['check_url']) ) {
      return false;
    }

    $cached_update = get_site_transient('omnivalt_latest_update');
    if ( is_array($cached_update) ) {
      if ( ! empty($cached_update['error']) ) {
        return false;
      }
      if ( ! empty($cached_update['version']) && array_key_exists('tag', $cached_update) && array_key_exists('changelog', $cached_update) ) {
        return $cached_update;
      }
    }

    $response = wp_remote_get($update_params['check_url'], array(
      'timeout' => 10,
      'headers' => array(
        'Accept' => 'application/vnd.github+json',
        'User-Agent' => 'Omniva-WooCommerce/' . OMNIVALT_VERSION,
      ),
    ));

    if ( is_wp_error($response) || 200 !== (int) wp_remote_retrieve_response_code($response) ) {
      set_site_transient('omnivalt_latest_update', array('error' => true), HOUR_IN_SECONDS);
      return false;
    }

    $response_data = json_decode(wp_remote_retrieve_body($response));
    if ( ! is_object($response_data) || empty($response_data->tag_name) ) {
      set_site_transient('omnivalt_latest_update', array('error' => true), HOUR_IN_SECONDS);
      return false;
    }

    $release_tag = (string) $response_data->tag_name;
    $release_notes = ( isset($response_data->body) && is_string($response_data->body) ) ? $response_data->body : '';
    $release_notes = wpautop(wp_kses_post($release_notes));

    $latest_update = array(
      'tag' => $release_tag,
      'version' => str_replace('v', '', $release_tag),
      'url' => ( ! empty($response_data->html_url) ) ? esc_url_raw($response_data->html_url) : '#',
      'package' => ( ! empty($update_params['download_url']) ) ? esc_url_raw($update_params['download_url']) : '',
      'last_updated' => ( ! empty($response_data->published_at) ) ? sanitize_text_field($response_data->published_at) : '',
      'changelog' => $release_notes,
    );

    set_site_transient('omnivalt_latest_update', $latest_update, 12 * HOUR_IN_SECONDS);

    return $latest_update;
  }

  public static function check_update( $current_version = '' )
  {
    $update_info = self::get_latest_update();

    if ( empty($update_info['version']) ) {
      return false;
    }

    if ( empty($current_version) ) {
      $plugin_data = get_file_data(OmnivaLt_Core::$main_file_path, array('Version' => 'Version'), '');
      $current_version = $plugin_data['Version'];
    }

    return ( version_compare($current_version, $update_info['version'], '<') ) ? $update_info : false;
  }

  public static function update_plugins( $transient )
  {
    $plugin_basename = self::get_plugin_basename();

    if ( '' === $plugin_basename || ! is_object($transient) || empty($transient->checked) || ! isset($transient->checked[$plugin_basename]) ) {
      return $transient;
    }

    $update_info = self::check_update($transient->checked[$plugin_basename]);
    if ( ! $update_info || empty($update_info['package']) ) {
      return $transient;
    }

    $update_info = self::enrich_update_info_with_github_metadata($update_info);

    $response = ( isset($transient->response) && is_array($transient->response) ) ? $transient->response : array();

    $plugin_update = array(
      'slug' => dirname($plugin_basename),
      'plugin' => $plugin_basename,
      'new_version' => $update_info['version'],
      'url' => $update_info['url'],
      'package' => $update_info['package'],
    );

    foreach ( array('requires', 'tested', 'requires_php') as $metadata_key ) {
      if ( ! empty($update_info[$metadata_key]) ) {
        $plugin_update[$metadata_key] = $update_info[$metadata_key];
      }
    }

    $response[$plugin_basename] = (object) $plugin_update;
    $transient_data = get_object_vars($transient);
    $transient_data['response'] = $response;

    return (object) $transient_data;
  }

  public static function plugin_information( $result, $action, $args )
  {
    $plugin_basename = self::get_plugin_basename();
    $plugin_slug = dirname($plugin_basename);

    if ( '' === $plugin_basename || 'plugin_information' !== $action || ! is_object($args) || empty($args->slug) || $plugin_slug !== $args->slug ) {
      return $result;
    }

    $update_info = self::get_latest_update();
    if ( ! $update_info ) {
      return $result;
    }

    $update_info = self::enrich_update_info_with_github_metadata($update_info);
    $requirements_section = '';

    if ( ! empty($update_info['wc_requires']) ) {
      $requirements_section .= '<p><strong>' . esc_html__('WC requires at least:', 'omnivalt') . '</strong> ' . esc_html($update_info['wc_requires']) . '</p>';
    }
    if ( ! empty($update_info['wc_tested']) ) {
      $requirements_section .= '<p><strong>' . esc_html__('WC tested up to:', 'omnivalt') . '</strong> ' . esc_html($update_info['wc_tested']) . '</p>';
    }

    $sections = array(
      'description' => __('Official Omniva shipping plugin for WooCommerce', 'omnivalt'),
      'changelog' => ! empty($update_info['changelog']) ? $update_info['changelog'] : __('See the release notes on GitHub.', 'omnivalt'),
    );
    if ( '' !== $requirements_section ) {
      $sections['requirements'] = $requirements_section;
    }

    $plugin_information = array(
      'name' => 'Omniva shipping',
      'slug' => $plugin_slug,
      'version' => $update_info['version'],
      'author' => 'Omniva',
      'homepage' => 'https://www.omniva.lt/en/business/integrations-for-e-shops',
      'download_link' => $update_info['package'],
      'last_updated' => $update_info['last_updated'],
      'sections' => $sections,
    );

    foreach ( array('requires', 'tested', 'requires_php') as $metadata_key ) {
      if ( ! empty($update_info[$metadata_key]) ) {
        $plugin_information[$metadata_key] = $update_info[$metadata_key];
      }
    }

    return (object) $plugin_information;
  }

  public static function update_message( $plugin_data, $response )
  {
    if ( ! is_array($plugin_data) || ! is_object($response) ) {
      return;
    }

    $update_params = self::get_update_params();
    if ( ! empty($update_params['download_url']) ) {
      echo '<br/><br/>' . sprintf(
        esc_html__('Or you can manually download the plugin %s.', 'omnivalt'),
        '<a href="' . esc_url($update_params['download_url']) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('here', 'omnivalt') . '</a>'
      );
    }

    $custom_changes = self::get_custom_changes();
    if ( ! empty($custom_changes) ) {
      echo '<br/><br/><strong style="color:red;">' . esc_html__('We do not recommend update the plugin, because your plugin have changes that is not included in the update', 'omnivalt') . ':</strong>';
      foreach ( $custom_changes as $change ) {
        if ( is_scalar($change) && '' !== (string) $change ) {
          echo '<br/>&middot; ' . esc_html($change);
        }
      }
    }
  }

  private static function enrich_update_info_with_github_metadata( $update_info )
  {
    if ( ! is_array($update_info) || isset($update_info['requires'], $update_info['tested'], $update_info['requires_php'], $update_info['wc_requires'], $update_info['wc_tested']) ) {
      return $update_info;
    }

    $update_params = self::get_update_params();
    $github_metadata = self::get_github_plugin_metadata(
      isset($update_info['tag']) ? $update_info['tag'] : '',
      isset($update_params['check_url']) ? $update_params['check_url'] : ''
    );
    $update_info = array_merge($update_info, $github_metadata);
    set_site_transient('omnivalt_latest_update', $update_info, 12 * HOUR_IN_SECONDS);

    return $update_info;
  }

  private static function get_github_plugin_metadata( $tag_name, $check_url )
  {
    $metadata = array(
      'requires' => '',
      'tested' => '',
      'requires_php' => '',
      'wc_requires' => '',
      'wc_tested' => '',
    );

    if ( ! is_string($tag_name) || '' === $tag_name || ! is_string($check_url) || '' === $check_url ) {
      return $metadata;
    }

    $check_url_parts = wp_parse_url($check_url);
    if ( ! is_array($check_url_parts) || empty($check_url_parts['host']) || 'api.github.com' !== strtolower($check_url_parts['host']) || empty($check_url_parts['path']) ) {
      return $metadata;
    }

    if ( ! preg_match('#^/repos/([^/]+/[^/]+)/releases/latest$#', $check_url_parts['path'], $repository_match) ) {
      return $metadata;
    }

    $metadata_url = 'https://raw.githubusercontent.com/' . $repository_match[1] . '/' . rawurlencode($tag_name) . '/' . ltrim(self::get_plugin_basename(), '/');
    $response = wp_remote_get($metadata_url, array(
      'timeout' => 10,
      'headers' => array(
        'Accept' => 'text/plain',
        'User-Agent' => 'Omniva-WooCommerce/' . OMNIVALT_VERSION,
      ),
    ));

    if ( is_wp_error($response) || 200 !== (int) wp_remote_retrieve_response_code($response) ) {
      return $metadata;
    }

    $plugin_source = wp_remote_retrieve_body($response);
    if ( ! is_string($plugin_source) || '' === $plugin_source ) {
      return $metadata;
    }

    $plugin_source = substr($plugin_source, 0, 8192);
    $header_names = array(
      'requires' => 'Requires at least',
      'tested' => 'Tested up to',
      'requires_php' => 'Requires PHP',
      'wc_requires' => 'WC requires at least',
      'wc_tested' => 'WC tested up to',
    );

    foreach ( $header_names as $metadata_key => $header_name ) {
      $header_pattern = '/^[ \t]*(?:\*[ \t]*)?' . preg_quote($header_name, '/') . '[ \t]*:[ \t]*(.+?)[ \t]*$/mi';
      if ( preg_match($header_pattern, $plugin_source, $header_match) ) {
        $metadata[$metadata_key] = sanitize_text_field($header_match[1]);
      }
    }

    return $metadata;
  }

  private static function get_plugin_basename()
  {
    if ( ! defined('OMNIVALT_BASENAME') ) {
      return '';
    }

    $plugin_basename = constant('OMNIVALT_BASENAME');
    return is_string($plugin_basename) ? $plugin_basename : '';
  }

  private static function get_update_params()
  {
    // The configuration is immutable during a request; avoid rebuilding all
    // shipping methods and API settings for each updater hook invocation.
    static $update_params = null;

    if ( null === $update_params ) {
      $configured_params = OmnivaLt_Core::get_configs('update');
      $update_params = is_array($configured_params) ? $configured_params : array();
    }

    return $update_params;
  }

  private static function get_custom_changes()
  {
    $constant_name = 'OMNIVALT_CUSTOM_CHANGES';
    if ( ! defined($constant_name) ) {
      return array();
    }

    $defined_constants = get_defined_constants(true);
    $custom_changes = isset($defined_constants['user'][$constant_name]) ? $defined_constants['user'][$constant_name] : null;
    return is_array($custom_changes) ? $custom_changes : array();
  }
}
