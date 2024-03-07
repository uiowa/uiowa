<?php

namespace Drupal\uiowa_facilities;

use Drupal\uiowa_facilities\BuildUiApiClientInterface;
use Drupal\uiowa_core\ApiClientBase;

/**
 * The BuildUI API service.
 */
class BuildUiApiClient extends ApiClientBase implements BuildUiApiClientInterface {

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

  /**
   * {@inheritdoc}
   */
  public function getFeaturedProjects(): array {
    return $this->get('featuredprojects');
  }

  /**
   * {@inheritdoc}
   */
  public function getCapitalProjects(): array {
    return $this->get('capitalprojects');
  }

  /**
   * {@inheritdoc}
   */
  public function getProjectInfo($project_id): mixed {
    return $this->get('projectinfo', [
      'query' => [
        'projnumber' => $project_id,
      ],
    ]);
  }

}
