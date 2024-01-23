<?php

namespace Drupal\facilities_core;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\field\FieldConfigInterface;
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
   * The file_system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fs;

  /**
   * The file_system service.
   *
   * @var \Drupal\field\FieldConfigInterface|null
   */
  protected ?FieldConfigInterface $imageFieldConfig;

  /**
   * The file_system service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

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
      // Request from Facilities API to get buildings.
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
    $this->fs = \Drupal::service('file_system');
    $this->configFactory = \Drupal::service('config.factory');
  }

  /**
   * {@inheritdoc}
   */
  protected function processEntity(ContentEntityInterface &$entity, $record): bool {
    return ProjectItemProcessor::process($entity, $record);
  }

}
