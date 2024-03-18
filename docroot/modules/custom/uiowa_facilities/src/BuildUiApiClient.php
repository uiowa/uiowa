<?php

namespace Drupal\uiowa_facilities;

use Drupal\uiowa_core\ApiClientBase;

/**
 * The BuildUI API service.
 */
class BuildUiApiClient extends ApiClientBase implements BuildUiApiClientInterface {

  /**
   * {@inheritdoc}
   */
  public function basePath(): string {
    return $this->configFactory->get('uiowa_facilities.apis')->get('buildui.endpoint');
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
  protected function loggerChannel(): string {
    return 'uiowa_facilities';
  }

  /**
   * {@inheritdoc}
   */
  public function getProjectsByBuilding($building_number): mixed {
    return $this->get('projects', [
      'query' => [
        'bldgnumber' => $building_number,
      ],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getFeaturedProjects(): array|bool {
    static::getLogger($this->loggerChannel())->info('Retrieving featured projects');
    return $this->get('featuredprojects');
  }

  /**
   * {@inheritdoc}
   */
  public function getCapitalProjects(): array|bool {
    static::getLogger($this->loggerChannel())->info('Retrieving capital projects');
    return $this->get('capitalprojects');
  }

  /**
   * {@inheritdoc}
   */
  public function getProjectInfo(string $project_id): mixed {
    return $this->get('projectinfo', [
      'query' => [
        'projnumber' => $project_id,
      ],
    ]);
  }

}
