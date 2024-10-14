<?php

namespace Drupal\uiowa_core;

use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * An interface for API client services.
 */
interface ApiClientInterface {

  /**
   * Returns the base path for the API with a trailing slash.
   *
   * @return string
   *   The base path.
   */
  public function basePath(): string;

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
   * @param string $endpoint
   *   The endpoint.
   * @param array $options
   *   The options.
   *
   * @return false|mixed
   *   The response data or FALSE.
   */
  public function get(string $endpoint, array $options = []);

  /**
   * Return the last API request response.
   *
   * @return \Psr\Http\Message\ResponseInterface|null
   *   The response object.
   */
  public function lastResponse(): ?ResponseInterface;

  /**
   * Return the API client.
   *
   * @return \GuzzleHttp\ClientInterface
   *   The Guzzle client object.
   */
  public function getClient(): ClientInterface;

  /**
   * Adds authentication to the options array.
   *
   * @param array $options
   *   The options array.
   *
   * @return void
   *   The options array.
   */
  public function addAuthToOptions(array &$options = []): void;

}
