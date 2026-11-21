<?php

/** Read-only access to the OMX tracking event stream. */
class OmnivaLt_Api_Omx_Tracking
{
  const URL = 'https://omx.omniva.eu/api/v01/omx/shipments';
  const TEST_URL = 'https://test-omx.omniva.eu/api/v01/omx/shipments';

  private $username;
  private $password;

  public function __construct( $username, $password )
  {
    $this->username = is_string($username) ? trim($username) : '';
    $this->password = is_string($password) ? $password : '';
  }

  public function get_events_since_id( $event_id )
  {
    $event_id = OmnivaLt_Tracking_Sync::event_id($event_id);
    if ( false === $event_id ) {
      return $this->failure('invalid_cursor');
    }
    return $this->get_page(array('size' => 100, 'sort' => 'eventId,asc', 'fromTrackEventId' => $event_id), $event_id);
  }

  public function get_events_since_time( $timestamp )
  {
    if ( ! is_string($timestamp) || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/D', $timestamp) ) {
      return $this->failure('invalid_timestamp');
    }
    return $this->get_page(array('size' => 100, 'sort' => 'eventId,asc', 'fromTimestamp' => $timestamp));
  }

  private function get_page( $query, $cursor = false )
  {
    $result = $this->request(add_query_arg($query, $this->base_url()));
    if ( ! $result['ok'] ) {
      return $result;
    }
    $body = $result['data'];
    if ( ! isset($body['content'], $body['last']) || ! is_array($body['content']) || ! is_bool($body['last'])
      || count($body['content']) > 100 || array_values($body['content']) !== $body['content'] ) {
      return $this->failure('invalid_response');
    }
    $last_id = false;
    foreach ( $body['content'] as $event ) {
      $id = OmnivaLt_Tracking_Sync::event_id(is_array($event) ? ($event['eventId'] ?? null) : null);
      if ( false === $id ) {
        continue;
      }
      if ( false === $last_id && false !== $cursor && OmnivaLt_Tracking_Sync::compare_ids($id, $cursor) <= 0 ) {
        return $this->failure('invalid_cursor_response');
      }
      if ( false !== $last_id && OmnivaLt_Tracking_Sync::compare_ids($id, $last_id) <= 0 ) {
        return $this->failure('unordered_events');
      }
      $last_id = $id;
    }
    if ( false === $last_id && (! $body['last'] || ! empty($body['content'])) ) {
      return $this->failure('invalid_response');
    }
    return $result;
  }

  private function request( $url )
  {
    if ( '' === $this->username || '' === $this->password ) {
      return $this->failure('missing_credentials');
    }
    $response = wp_remote_get($url, array(
      'timeout' => 15,
      'redirection' => 0,
      'headers' => array(
        // HTTP Basic authentication requires Base64-encoded credentials.
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
        'Authorization' => 'Basic ' . base64_encode($this->username . ':' . $this->password),
        'Accept' => 'application/json',
        'X-Integration-Agent-Id' => '7005511 WooCommerce v' . OMNIVALT_VERSION,
      ),
    ));
    if ( is_wp_error($response) ) {
      return $this->failure('transport');
    }
    $status = wp_remote_retrieve_response_code($response);
    if ( 200 !== $status ) {
      return array('ok' => false, 'error' => 'http', 'http_status' => $status);
    }
    $body = json_decode(wp_remote_retrieve_body($response), true, 512, JSON_BIGINT_AS_STRING);
    if ( ! is_array($body) || JSON_ERROR_NONE !== json_last_error() ) {
      return $this->failure('invalid_response');
    }
    return array('ok' => true, 'data' => $body);
  }

  private function failure( $error )
  {
    return array('ok' => false, 'error' => $error);
  }

  private function base_url()
  {
    return defined('_OMNIVA_API_USE_TEST_OMX_') && _OMNIVA_API_USE_TEST_OMX_ ? self::TEST_URL : self::URL;
  }
}
