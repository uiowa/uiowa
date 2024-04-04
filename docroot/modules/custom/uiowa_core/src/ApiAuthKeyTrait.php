<?php

namespace Drupal\uiowa_core;

/**
 * A trait for adding key-based authentication to an API client.
 */
trait ApiAuthKeyTrait {

  /**
   * The API key for accessing the API.
   *
   * @var string|null
   */
  protected ?string $apiKey = NULL;

  /**
   * {@inheritdoc}
   */
  public function getKey(): string|null {
    return $this->apiKey;
  }

  /**
   * {@inheritdoc}
   */
  public function setKey($key): static {
    $this->apiKey = $key;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addAuthToOptions(&$options): void {
    if (!is_null($this->apiKey)) {
      // Merge additional options with default but allow overriding.
      $options = array_merge([
        'headers' => [
          'x-dispatch-api-key' => $this->apiKey,
        ],
      ], $options);
    }
  }

}
