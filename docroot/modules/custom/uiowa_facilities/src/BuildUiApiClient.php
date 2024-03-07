<?php

namespace Drupal\uiowa_facilities;

use Drupal\sitenow_dispatch\BuildUiApiClientInterface;
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
   * Get all featured projects.
   *
   * @return array
   *   The featured projects object.
   */
  public function getFeaturedProjects(): array {
    return $this->get('featuredprojects');
  }

  /**
   * Get all capital projects.
   *
   * @return array
   *   The capital projects object.
   */
  public function getCapitalProjects(): array {
    return $this->get('capitalprojects');
  }

  /**
   * @param $project_id
   *
   * @return false|mixed
   */
  public function getProjectInfo($project_id) {
    return $this->get('projectinfo', [
      'query' => [
        'projnumber' => $project_id,
      ],
    ]);
  }

}
