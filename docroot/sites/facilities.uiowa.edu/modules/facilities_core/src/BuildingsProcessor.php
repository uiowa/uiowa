<?php

namespace Drupal\facilities_core;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\FieldConfigInterface;
use Drupal\uiowa_core\EntityProcessorBase;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

/**
 * Sync building information.
 */
class BuildingsProcessor extends EntityProcessorBase {

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
  public string $bundle = 'building';

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
      // Request from Facilities API to get buildings.
      $facilities_api = \Drupal::service('uiowa_facilities.api');
      $this->data = $facilities_api->getBuildings();
    }
    return $this->data;
  }

  /**
   * {@inheritdoc}
   */
  protected function processRecord(&$record) {
    if (!is_null($building_number = $record?->{$this->apiRecordSyncKey})) {
      // Request from Facilities API to get buildings.
      $facilities_api = \Drupal::service('uiowa_facilities.api');
      $result = $facilities_api->getBuilding($building_number);
      // Get image
      // Use some type of caching strategy.
      $this->processResultImage($result);
      foreach ((array) $result as $key => $value) {
        $record->{$key} = $value;
      }
    }

    // There is at least one building with a blank space instead of
    // NULL for this value.
    // @todo Remove if FM can clean up their source.
    // https://github.com/uiowa/uiowa/issues/6084
    if ($record->buildingAbbreviation === '') {
      $record->buildingAbbreviation = NULL;
    }

    // If the namedBuilding field is not NULL, it needs to be converted to a
    // entity ID for an existing named building.
    if (isset($record->namedBuilding)) {
      $record->namedBuilding = $this->findNamedBuildingNid($record->{$this->apiRecordSyncKey});
    }
  }

  /**
   * Initialize relevant services.
   */
  public function init() {
    $this->client = \Drupal::service('http_client');
    $this->fs = \Drupal::service('file_system');
    $this->configFactory = \Drupal::service('config.factory');
    $this->imageFieldConfig = FieldConfig::loadByName('node', 'building', 'field_building_image');
  }

  /**
   * Cache local image from API.
   *
   * Save a local version of an image gotten from the facilities API
   * and assign that as the building image. Additionally, add title
   * and alt text to the image field based on API data.
   *
   * @param array $result
   *   The result array reference that contains the image URL.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *    Throws an exception if it can't get the image for the building.
   */
  protected function processResultImage(&$result) {
    if (!empty($result->imageUrl)) {
      try {
        $building_image_url = $result->imageUrl;
        $this->client->request('GET', $building_image_url);

        $scheme = $this->configFactory->get('system.file')->get('default_scheme');
        $destination = $scheme . '://' . $this->imageFieldConfig->getSetting('file_directory') . '/';
        $building_number = $result->buildingNumber;
        $realpath = $this->fs->realpath($destination);

        if ($this->fs->prepareDirectory($realpath, FileSystemInterface::CREATE_DIRECTORY)) {
          $data = file_get_contents($building_image_url);
          $file = \Drupal::service('file.repository')->writeData($data, "{$destination}{$building_number}.jpg", FileSystemInterface::EXISTS_REPLACE);

          $building_formal_name = $result?->buildingFormalName ?: '';
          $alt_text = $building_formal_name;

          $result->imageUrl = [
            'target_id' => $file->id(),
            'title' => $building_formal_name,
            'alt' => $alt_text,
          ];
        }
      }
      catch (ClientException $e) {
        $this->getLogger('facilities_core')->warning($this->t('Unable to get image for @building.', [
          '@building' => $result?->buildingNumber . ' : ' . $result?->buildingFormalName,
        ]));

        // Use the default thumbnail if we can't get one.
        $result->imageUrl = '';
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function processEntity(ContentEntityInterface &$entity, $record): bool {
    return BuildingItemProcessor::process($entity, $record);
  }

  /**
   * Find a named build node ID based on a first name and last name.
   *
   * @param string $string
   *   The string being searched.
   *
   * @return int|null
   *   The entity ID of the named building, if it exists.
   */
  protected function findNamedBuildingNid($string) {
    $nids = \Drupal::entityQuery('node')
      ->condition('type', 'named_building')
      ->condition('field_building_building_id', $string)
      ->accessCheck()
      ->execute();

    foreach ($nids as $nid) {
      return $nid;
    }

    return NULL;
  }

}
