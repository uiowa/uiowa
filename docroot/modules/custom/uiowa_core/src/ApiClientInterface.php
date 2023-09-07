<?php

namespace Drupal\uiowa_core;

/**
 * An interface for API client services.
 */
interface ApiClientInterface {
  /**
   * Make a Dispatch API request and return JSON-decoded data.
   *
   * @param string $method
   *   The HTTP method to use.
   * @param string $endpoint
   *   The entire API endpoint URL or just the path relative to the base URL.
   * @param array $params
   *   Optional URI query parameters.
   * @param array $options
   *   Additional request options. Accept and API key set automatically.
   *
   * @return mixed
   *   The API response data or FALSE.
   */
  public function request(string $method, string $endpoint, array $options = []);
}
