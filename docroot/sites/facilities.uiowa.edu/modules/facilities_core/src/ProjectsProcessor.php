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
  public function __construct() {
    parent::__construct($this->bundle);
  }

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
  protected function processRecord(&$record) {
    if (!is_null($project_number = $record?->{$this->apiRecordSyncKey})) {
      // Request from Facilities API to get projects.
      $facilities_api = \Drupal::service('uiowa_facilities.api');
      $result = $facilities_api->getProjects($project_number);
      foreach ((array) $result as $key => $value) {
        $record->{$key} = $value;
      }
    }
  }

  /**
   * Initialize relevant services.
   */
  public function init() {
    $this->client = \Drupal::service('http_client');
  }

  /**
   * {@inheritdoc}
   */
  protected function processEntity(ContentEntityInterface &$entity, $record): bool {
    // Compare the record to the entity, but skip over projectType.
    return ProjectItemProcessor::process($entity, $record, ['projectType']);
  }

}
