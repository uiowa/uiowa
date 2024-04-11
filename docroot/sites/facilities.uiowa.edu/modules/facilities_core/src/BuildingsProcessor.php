<?php

namespace Drupal\facilities_core;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\FieldConfigInterface;
use Drupal\uiowa_core\EntityProcessorBase;
use Drupal\uiowa_facilities\BizHubApiClient;
use GuzzleHttp\Exception\ClientException;

/**
 * Sync building information.
 */
class BuildingsProcessor extends EntityProcessorBase {

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
   * A list of building coordinators.
   *
   * @var array|null
   */
  protected $buildingCoordinators = NULL;

  /**
   * The BizHub API client.
   *
   * @var \Drupal\uiowa_facilities\BizHubApiClientInterface
   */
  protected BizHubApiClient $apiClient;

  /**
   * {@inheritdoc}
   */
  public function __construct() {
    parent::__construct();
    $this->apiClient = \Drupal::service('uiowa_facilities.bizhub_api_client');
    $this->fs = \Drupal::service('file_system');
    $this->configFactory = \Drupal::service('config.factory');
    $this->imageFieldConfig = FieldConfig::loadByName('node', 'building', 'field_building_image');
  }

  /**
   * {@inheritdoc}
   */
  protected function getData() {
    if (!isset($this->data)) {
      // Request from BizHub API to get buildings.
      $this->data = $this->apiClient->getBuildings();
    }
    return $this->data;
  }

  /**
   * {@inheritdoc}
   */
  protected function processRecord(&$record) {
    if (!is_null($building_number = $record?->{$this->apiRecordSyncKey})) {
      // Request from BizHub API to get building.
      $result = $this->apiClient->getBuilding($building_number);
      // Get image
      // Use some type of caching strategy.
      $this->processResultImage($result);
      foreach ((array) $result as $key => $value) {
        $record->{$key} = $value;
      }
    }
    // API call for building coordinator information.
    $coordinators = $this->getCoordinatorsForBuilding($building_number);

    // Merge building coordinators data into the building record for processing.
    $coordinator_properties = [
      'mainFullName', 'mainJobTitle', 'mainDepartment',
      'mainCampusEmail', 'mainCampusPhone', 'alternateFullName1',
      'alternateJobTitle1', 'alternateDepartment1', 'alternateCampusEmail1',
      'alternateCampusPhone1', 'alternateFullName2',
      'alternateJobTitle2', 'alternateDepartment2', 'alternateCampusEmail2',
      'alternateCampusPhone2', 'alternateFullName3',
      'alternateJobTitle3', 'alternateDepartment3', 'alternateCampusEmail3',
      'alternateCampusPhone3', 'alternateFullName4',
      'alternateJobTitle4', 'alternateDepartment4', 'alternateCampusEmail4',
      'alternateCampusPhone4', 'maintenanceManagerFullName', 'custodialAssistantManagerFullName',
    ];

    foreach ($coordinator_properties as $property) {
      $record->{$property} = $coordinators->{$property} ?? NULL;
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
      $record->namedBuilding = $this->findNamedBuildingNid($record->id);
    }
  }

  /**
   * Cache local image from API.
   *
   * Save a local version of an image gotten from the facilities API
   * and assign that as the building image. Additionally, add
   * alt text to the image field based on API data.
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
        $scheme = $this->configFactory->get('system.file')->get('default_scheme');
        $destination = $scheme . '://' . $this->imageFieldConfig->getSetting('file_directory') . '/';
        $building_number = $result->buildingNumber;
        $realpath = $this->fs->realpath($destination);

        if ($this->fs->prepareDirectory($realpath, FileSystemInterface::CREATE_DIRECTORY)) {
          $data = file_get_contents($result->imageUrl);
          $file = \Drupal::service('file.repository')->writeData($data, "{$destination}{$building_number}.jpg", FileSystemInterface::EXISTS_REPLACE);

          $building_formal_name = $result?->buildingFormalName ?: '';

          $result->imageUrl = [
            'target_id' => $file->id(),
            'alt' => $building_formal_name,
          ];
        }
      }
      catch (ClientException $e) {
        $this->logger()->warning($this->t('Unable to get image for @building.', [
          '@building' => $result?->buildingNumber . ' : ' . $result?->buildingFormalName,
        ]));

        // Use the default thumbnail if we can't get one.
        $result->imageUrl = '';
      }
    }
    else {
      $result->imageUrl = NULL;
    }
  }

  /**
   * Get the coordinator results for a building.
   *
   * If the results have not already been fetched from the API and processed,
   * that happens first.
   *
   * @param string $building_number
   *   The building number.
   *
   * @return array
   *   The list of coordinators or an empty array.
   */
  protected function getCoordinatorsForBuilding(string $building_number): array {
    // If building coordinators have not already been fetched, fetch them and
    // process them into an array keyed by building number.
    if (is_null($this->buildingCoordinators)) {
      $this->buildingCoordinators = [];
      $results = $this->apiClient->getBuildingCoordinators();
      foreach ($results as $result) {
        $this->buildingCoordinators[$result->buildingNumber] = (array) $result;
      }
    }

    // Return the result if it exists or an empty array.
    return $this->buildingCoordinators[$building_number] ?? [];
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
