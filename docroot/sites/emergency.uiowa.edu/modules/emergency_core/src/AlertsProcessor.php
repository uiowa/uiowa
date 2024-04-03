<?php

namespace Drupal\emergency_core;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\FieldConfigInterface;
use Drupal\uiowa_core\EntityProcessorBase;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

/**
 * Sync alert information.
 */
class AlertsProcessor extends EntityProcessorBase {

  /**
   * The http_client service.
   *
   * @var \GuzzleHttp\Client
   */
  protected Client $client;

  /**
   * {@inheritdoc}
   */
  public string $bundle = 'hawk_alert';

  /**
   * {@inheritdoc}
   */
  public function __construct() {
    parent::__construct($this->bundle);
  }

  /**
   * {@inheritdoc}
   */
  protected $fieldSyncKey = 'title';

  /**
   * {@inheritdoc}
   */
  protected $apiRecordSyncKey = 'headline';

  /**
   * {@inheritdoc}
   */
  protected function getData() {
    if (!isset($this->data)) {
      $rave_api = \Drupal::service('uiowa_rave.api');
      $this->data = $rave_api->getHawkAlerts();
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
    return AlertItemProcessor::process($entity, $record);
  }

}
