<?php

namespace Drupal\uiowa_core;

/**
 * A trait for adding basic authentication to an API client.
 */
trait ApiAuthBasicTrait {

  /**
   * The username for basic authentication.
   *
   * @var string|null
   */
  protected ?string $username = NULL;

  /**
   * The password for basic authentication.
   *
   * @var string|null
   */
  protected ?string $password = NULL;

  /**
   * {@inheritdoc}
   */
  public function addAuthToOptions(&$options): void {
    if (!is_null($this->username) && !is_null($this->password)) {
      $options['auth'] = [$this->username, $this->password];
    }
  }
}
