<?php

namespace Drupal\sitenow_signage;

use Drupal\uiowa_core\ApiClientBase;

/**
 * IC Area Transit API.
 */
class ICAreaTransitApiClient extends ApiClientBase {

  /**
   * {@inheritdoc}
   */
  public function basePath(): string {
    return 'https://api.icareatransit.org/';
  }

  /**
   * {@inheritdoc}
   */
  protected function getCacheIdBase() {
    return 'sitenow_signage_icareatransit';
  }

  /**
   * Get a list of bus stops.
   */
  public function getStopList(): array {
    $response = $this->get('stoplist');
    return $response->stops;
  }

}
