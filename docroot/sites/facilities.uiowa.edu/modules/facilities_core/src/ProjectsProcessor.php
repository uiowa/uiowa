<?php

namespace Drupal\facilities_core;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\node\Entity\Node;
use Drupal\uiowa_core\EntityProcessorBase;
use Drupal\uiowa_facilities\BuildUiApiClientInterface;

/**
 * Sync building information.
 */
class ProjectsProcessor extends EntityProcessorBase {

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
   * The BuildUI API client.
   *
   * @var \Drupal\uiowa_facilities\BuildUiApiClientInterface
   */
  protected BuildUiApiClientInterface $apiClient;

  /**
   * {@inheritdoc}
   */
  public function __construct() {
    parent::__construct();
    $this->apiClient = \Drupal::service('uiowa_facilities.buildui_api_client');
  }

  /**
   * {@inheritdoc}
   */
  protected function getData() {
    if (!isset($this->data)) {
      $this->data = $this->getProjects();
    }
    return $this->data;
  }

  /**
   * Get all projects.
   *
   * @return array|false
   *   The projects object.
   */
  public function getProjects(): bool|array {

    // Get all capital projects.
    if (FALSE === $capital_projects = $this->apiClient->getCapitalProjects()) {
      return FALSE;
    }
    // Get all featured projects.
    if (FALSE === $featured_projects = $this->apiClient->getFeaturedProjects()) {
      return FALSE;
    }

    $building_numbers = $this->getAllBuildingNumbers();
    $projects = [];

    // Function to transform dates.
    $transform_date = function ($timestamp) {
      if (!is_null($timestamp)) {
        $date_formatter = \Drupal::service('date.formatter');
        return $date_formatter->format($timestamp / 1000, 'custom', 'Y-m-d');
      }
      return NULL;
    };

    foreach ($building_numbers as $number => $nid) {
      // Use each number to make a query.
      if (FALSE === $building_projects = $this->apiClient->getProjectsByBuilding($number)) {
        return FALSE;
      }

      // Check if the response array is not empty.
      if (!empty($building_projects)) {
        // If the response contains multiple arrays, loop through each of them.
        foreach ($building_projects as $project) {
          $project->projectType = $project->projectType ?? NULL;

          // Add the project to the projects array.
          $projects[] = $project;
        }
      }
    }

    foreach ($featured_projects as $project) {
      $project->isFeatured = TRUE;
      $projects[] = $project;
    }

    foreach ($capital_projects as $project) {
      // Check if the project is already in the featured projects array.
      $is_featured = FALSE;
      foreach ($featured_projects as $featured_project) {
        if ($project->buiProjectId === $featured_project->buiProjectId) {
          $is_featured = TRUE;
          break;
        }
      }

      $project->isCapital = TRUE;

      if ($is_featured) {
        $project->isFeatured = TRUE;
      }

      $projects[] = $project;
    }

    // Grab additional fields listed at projectinfo and add them to the array.
    foreach ($projects as &$project) {
      $projectinfo_request = $this->apiClient->getProjectInfo($project->buiProjectId);

      if (!empty($projectinfo_request)) {
        // Merge the additional fields into the project.
        $project = (object) array_merge((array) $project, (array) $projectinfo_request);

        // Adjust square ft and estimated amount numbers.
        $project->grossSqFeet = $project->grossSqFeet == 0 ? NULL : strval($project->grossSqFeet);
        if ($project->estimatedAmount !== NULL) {
          $project->estimatedAmount = floatval($project->estimatedAmount);
        }

        // Transform dates if they exist.
        $date_fields = [
          'bidOpeningDate',
          'constructionStartDate',
          'preBidDate',
          'substantialCompletionDate',
        ];
        foreach ($date_fields as $date_field) {
          $project->{$date_field} = $transform_date($project->{$date_field});
        }

        // Compare $field_building_number with $project->buildingNumber
        // and set the projectBuilding value to the node id.
        foreach ($building_numbers as $field_building_number => $nid) {
          if ($field_building_number == $project->buildingNumber) {
            // Set node id for building reference.
            $project->projectBuilding = $nid;
          }
        }
      }

      // Provide default values for common undefined properties.
      $default_properties = [
        'projectBuilding', 'grossSqFeet', 'preBidLocation',
        'vendorName', 'primaryConsultant', 'bidOpeningDate',
        'constructionStartDate', 'preBidDate', 'substantialCompletionDate',
        'estimatedAmount', 'isFeatured', 'isCapital',
      ];

      foreach ($default_properties as $property) {
        $project->{$property} = $project->{$property} ?? NULL;
      }
    }

    $projects_by_id = [];
    unset($project);
    foreach ($projects as $project) {
      $projects_by_id[$project->buiProjectId] = $project;
    }

    // Return the array of unique projects.
    return array_values($projects_by_id);
  }

  /**
   * Get all 'field_building_number' from the 'building' content type.
   *
   * @return array
   *   The array of building numbers.
   */
  public function getAllBuildingNumbers(): array {
    $query = \Drupal::entityQuery('node')
      ->condition('status', 1)
      ->condition('type', 'building')
      ->accessCheck(FALSE);
    $nids = $query->execute();

    $building_numbers = [];
    foreach ($nids as $nid) {
      $node = Node::load($nid);
      $field_building_number = $node->get('field_building_number')->value;
      // Map the building number to the node id.
      $building_numbers[$field_building_number] = $nid;
    }

    return $building_numbers;
  }

  /**
   * {@inheritdoc}
   */
  protected function processEntity(ContentEntityInterface &$entity, $record): bool {
    return ProjectItemProcessor::process($entity, $record);
  }

}
