<?php

namespace Drupal\uiowa_core;

/**
 * An interface for API client services.
 */
interface ApiClientInterface {

  /**
   * Performs and API request and returns response data.
   *
   * @param string $method
   *   The HTTP method to use.
   * @param string $endpoint
   *   The entire API endpoint URL or just the path relative to the base URL.
   * @param array $options
   *   Additional request options. Accept and API key set automatically.
   *
   * @return mixed
   *   The API response data or FALSE.
   */
  public function request(string $method, string $endpoint, array $options = []);

  /**
   * Performs a 'GET' request and returns response data.
   *
   * @param $endpoint
   *   The endpoint.
   * @param array $options
   *   The options.
   *
   * @return false|mixed
   *   The response data or FALSE.
   */
  public function get(string $endpoint, array $options = []);

}
