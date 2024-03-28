<?php

namespace Drupal\uiowa_core;

interface ApiAuthKeyInterface {

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

}
