<?php

namespace Drupal\uiowa_facilities;

use Drupal\uiowa_core\ApiClientBase;

/**
 * The BuildUI API service.
 */
class BuildUiApiClient extends ApiClientBase {

  /**
   * {@inheritdoc}
   */
  public function basePath(): string {
    return $this->configFactory->get('uiowa_facilities.api_endpoints')->get('buildui');
  }

  /**
   * {@inheritdoc}
   */
  protected function getCacheIdBase() {
    return 'uiowa_facilities_api_buildui';
  }

}
