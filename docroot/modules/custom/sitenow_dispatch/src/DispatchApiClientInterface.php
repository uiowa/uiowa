<?php

namespace Drupal\sitenow_dispatch;

interface DispatchApiClientInterface {

  /**
   * Get the API key.
   */
  public function getApiKey(): string|NULL;

  /**
   * Set the API key.
   *
   * @param $key
   *   The API key being set.
   *
   * @return \Drupal\sitenow_dispatch\DispatchApiClientInterface
   *   The DispatchApiClientInterface object.
   */
  public function setApiKey($key): DispatchApiClientInterface;

  /**
   * Make a Dispatch API request and return JSON-decoded data.
   *
   * @param  string  $method
   *    The HTTP method to use.
   * @param  string  $endpoint
   *    The entire API endpoint URL or just the path relative to the base URL.
   * @param  array  $params
   *    Optional URI query parameters.
   * @param  array  $options
   *    Additional request options. Accept and API key set automatically.
   *
   * @return mixed
   *    The API response data or FALSE.
   */
  public function request(string $method, string $endpoint, array $params = [], array $options = []);

  /**
   * Return a list of campaigns keyed by endpoint.
   */
  public function getCampaigns();

  /**
   * Return a list of campaigns keyed by endpoint.
   */
  public function getCommunications($campaign);

  /**
   * Return details about a communication.
   */
  public function getCommunication($id);

  /**
   * Return a list of populations keyed by endpoint.
   */
  public function getPopulations();

  /**
   * Return a list of suppression lists keyed by endpoint.
   */
  public function getSuppressionLists();

  /**
   * Return a list of suppression lists keyed by endpoint.
   */
  public function getTemplates();
}
