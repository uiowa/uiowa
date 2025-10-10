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
   * The header parameter name for the api token.
   *
   * @return string
   *   The header api token key.
   */
  protected function headerParameterName(): string {
    return 'x-auth-token';
  }

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
  public function addAuthToOptions(array &$options = []): void {
    if (!is_null($this->apiKey)) {
      // Merge additional options with default but allow overriding.
      $options = array_merge([
        'headers' => [
          $this->headerParameterName() => $this->apiKey,
        ],
      ], $options);
    }
  }

}
