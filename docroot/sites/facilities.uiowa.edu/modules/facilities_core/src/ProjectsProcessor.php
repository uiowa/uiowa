<?php

namespace Drupal\facilities_core;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\uiowa_core\EntityProcessorBase;
use GuzzleHttp\Client;

/**
 * Sync building information.
 */
class ProjectsProcessor extends EntityProcessorBase {

  /**
   * The http_client service.
   *
   * @var \GuzzleHttp\Client
   */
  protected Client $client;

  /**
   * {@inheritdoc}
   */
  public string $bundle = 'project';

  /**
   * {@inheritdoc}
   */
  protected $fieldSyncKey = 'field_project_number';

  /**
   * {@inheritdoc}
   */
  protected $apiRecordSyncKey = 'buiProjectId';

  /**
   * {@inheritdoc}
   */
  protected function getData() {
    if (!isset($this->data)) {
      $facilities_api = \Drupal::service('uiowa_facilities.api');
      $this->data = $facilities_api->getProjects();
    }
    return $this->data;
  }

  /**
   * {@inheritdoc}
   */
  protected function processEntity(ContentEntityInterface &$entity, $record): bool {
    return ProjectItemProcessor::process($entity, $record);
  }

}
