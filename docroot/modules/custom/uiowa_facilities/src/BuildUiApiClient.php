<?php

namespace Drupal\uiowa_facilities;

use Drupal\uiowa_core\ApiClientBase;

class BuildUiApiClient extends ApiClientBase {

  /**
   * @inheritDoc
   */
  public function basePath(): string {
    return $this->configFactory->get('uiowa_facilities.api_endpoints')->get('buildui');
  }

  /**
   * @inheritDoc
   */
  protected function getCacheIdBase() {
    return 'uiowa_facilities_api_buildui';
  }

}
