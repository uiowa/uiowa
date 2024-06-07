<?php

namespace Drupal\registrar_core;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\uiowa_core\EntityProcessorBase;
use Drupal\uiowa_events\ContentHubApiClientInterface;

/**
 * Sync event information.
 */
class AcademicDatesProcessor extends EntityProcessorBase {

  /**
   * {@inheritdoc}
   */
  public string $bundle = 'academic_date';

  /**
   * {@inheritdoc}
   */
  protected $fieldSyncKey = 'field_event_id';

  /**
   * {@inheritdoc}
   */
  protected $apiRecordSyncKey = 'id';

  /**
   * A reusable timezone object.
   *
   * @var \DateTimeZone|null
   *   The timezone.
   */
  protected $timezone = NULL;

  /**
   * The Content Hub API client.
   *
   * @var \Drupal\uiowa_events\ContentHubApiClientInterface
   */
  protected ContentHubApiClientInterface $apiClient;

  /**
   * {@inheritdoc}
   */
  public function __construct() {
    parent::__construct();
    $this->apiClient = \Drupal::service('uiowa_maui.api');
    $this->timezone = new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE);
  }

  /**
   * {@inheritdoc}
   */
  protected function getData() {
    if (!isset($this->data)) {
      $this->data = [];
      // @todo Add processing logic.
    }
    return $this->data;
  }

  /**
   * {@inheritdoc}
   */
  protected function processEntity(ContentEntityInterface &$entity, $record): bool {
    return AcademicDateItemProcessor::process($entity, $record);
  }

  /**
   * {@inheritdoc}
   */
  protected function processRecord(&$record) {
    // @todo Add processing logic, if necessary.
  }

}
