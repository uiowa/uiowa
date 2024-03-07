<?php

namespace Drupal\uiowa_core;

use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * An interface for API client services.
 */
interface ApiClientInterface {

  /**
   * Get the API key.
   */
  public function getKey(): string|NULL;

  /**
   * Set the API key.
   *
   * @param string $key
   *   The API key being set.
   *
   * @return \Drupal\uiowa_core\ApiClientInterface
   *   The DispatchApiClientInterface object.
   */
  public function setKey(string $key): static;

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
   */
  public function getClient(): ClientInterface;

}
