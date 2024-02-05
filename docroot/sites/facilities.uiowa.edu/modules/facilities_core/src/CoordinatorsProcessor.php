<?php

namespace Drupal\facilities_core;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\uiowa_core\EntityProcessorBase;
use GuzzleHttp\Client;

/**
 * Sync building coordinators information.
 */
class CoordinatorsProcessor extends EntityProcessorBase {

  /**
   * The http_client service.
   *
   * @var \GuzzleHttp\Client
   */
  protected Client $client;

  /**
   * {@inheritdoc}
   */
  public string $bundle = 'coordinator';

  /**
   * {@inheritdoc}
   */
  public function __construct() {
    parent::__construct($this->bundle);
  }

  /**
   * {@inheritdoc}
   */
  protected $fieldSyncKey = 'field_building_number';

  /**
   * {@inheritdoc}
   */
  protected $apiRecordSyncKey = 'buildingNumber';

  /**
   * {@inheritdoc}
   */
  protected function getData() {
    if (!isset($this->data)) {
      // Request from Facilities API to get coordinators.
      $facilities_api = \Drupal::service('uiowa_facilities.api');
      $this->data = $facilities_api->getBuildingCoordinators();
    }
    return $this->data;
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
    return CoordinatorItemProcessor::process($entity, $record);
  }

}
