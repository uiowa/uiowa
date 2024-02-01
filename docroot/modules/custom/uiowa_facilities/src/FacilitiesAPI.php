<?php

namespace Drupal\uiowa_facilities;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\node\Entity\Node;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;

/**
 * Facilities API service.
 */
class FacilitiesAPI {

  const BASE_URL_1 = 'https://bizhub.facilities.uiowa.edu/bizhub/ext/';
  const BASE_URL_2 = 'https://buildui.facilities.uiowa.edu/buildui/ext/';

  /**
   * The uiowa_facilities logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The uiowa_facilities cache.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $client;

  /**
   * Constructs a FM object.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The uiowa_facilities logger channel.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The uiowa_facilities cache.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   */
  public function __construct(LoggerInterface $logger, CacheBackendInterface $cache, ClientInterface $http_client) {
    $this->logger = $logger;
    $this->cache = $cache;
    $this->client = $http_client;
  }

  /**
   * Make a Facilities API request and return data.
   *
   * @param string $method
   *   The HTTP method to use.
   * @param string $path
   *   The API path to use. Do not include the base URL.
   * @param array $params
   *   Optional request parameters.
   * @param array $options
   *   Optional request options. All requests expect JSON response data.
   * @param string $base
   *   The base URL to use for the request. Defaults to self::BASE_URL_1.
   *
   * @return mixed
   *   The API response data.
   */
  public function request($method, $path, array $params = [], array $options = [], $base = self::BASE_URL_1) {
    // Encode any special characters and trim duplicate slash.
    $path = UrlHelper::encodePath($path);
    $uri = $base . ltrim($path, '/');

    // Append any query string parameters.
    if (!empty($params)) {
      $query = UrlHelper::buildQuery($params);
      $uri .= "?{$query}";
    }

    // Merge additional options with default but allow overriding.
    $options = array_merge([
      'headers' => [
        'Accept' => 'application/json',
      ],
    ], $options);

    // Create a hash for the CID. Can always be decoded for debugging purposes.
    $hash = base64_encode($uri . serialize($options));
    $cid = "uiowa_facilities:request:{$hash}";
    // Default $data to FALSE in case of API fetch failure.
    $data = FALSE;

    if ($cache = $this->cache->get($cid)) {
      $data = $cache->data;
    }
    else {
      try {
        $response = $this->client->request($method, $uri, $options);
      }
      catch (RequestException | GuzzleException $e) {
        $this->logger->error('Error encountered getting data from @endpoint: @code @error', [
          '@endpoint' => $uri,
          '@code' => $e->getCode(),
          '@error' => $e->getMessage(),
        ]);
      }

      if (isset($response)) {
        $contents = $response->getBody()->getContents();

        /** @var object $data */
        $data = json_decode($contents);

        // Cache for 15 minutes.
        $this->cache->set($cid, $data, time() + 900);
      }
    }

    return $data;
  }

  /**
   * Get all buildings.
   *
   * @return array
   *   The buildings object.
   */
  public function getBuildings() {
    return $this->request('GET', 'buildings');
  }

  /**
   * Get single building by number.
   *
   * @return array
   *   The building object.
   */
  public function getBuilding($building_number) {
    return $this->request('GET', 'building', [
      'bldgnumber' => $building_number,
    ]);
  }

  /**
   * Get all featured projects.
   *
   * @return array
   *   The featured projects object.
   */
  public function getFeaturedProjects() {
    return $this->request('GET', 'featuredprojects', [], [], self::BASE_URL_2);
  }

  /**
   * Get all capital projects.
   *
   * @return array
   *   The capital projects object.
   */
  public function getCapitalProjects() {
    return $this->request('GET', 'capitalprojects', [], [], self::BASE_URL_2);
  }

  /**
   * Get all 'field_building_number' from the 'building' content type.
   *
   * @return array
   *   The array of building numbers.
   */
  public function getAllBuildingNumbers() {
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
   * Get all projects.
   *
   * @return array
   *   The projects object.
   */
  public function getProjects() {
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
      $response = $this->request('GET', 'projects', ['bldgnumber' => $number], [], self::BASE_URL_2);

      // Check if the response array is not empty.
      if (!empty($response)) {
        // If the response contains multiple arrays, loop through each of them.
        foreach ($response as $project) {
          $project->projectType = $project->projectType ?? NULL;

          // Add the project to the projects array.
          $projects[] = $project;
        }
      }
    }

    // Get all featured projects.
    $featured_projects = $this->getFeaturedProjects();
    foreach ($featured_projects as $project) {
      $project->isFeatured = TRUE;
      $projects[] = $project;
    }

    // Get all capital projects.
    $capital_projects = $this->getCapitalProjects();
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
      $projectinfo_request = $this->request('GET', 'projectinfo', ['projnumber' => $project->buiProjectId], [], self::BASE_URL_2);

      if (!empty($projectinfo_request)) {
        // Merge the additional fields into the project.
        $project = (object) array_merge((array) $project, (array) $projectinfo_request);

        // Adjust square ft and estimated amount numbers.
        $project->grossSqFeet = $project->grossSqFeet == 0 ? NULL : strval($project->grossSqFeet);
        if ($project->estimatedAmount !== NULL) {
          $project->estimatedAmount = floatval($project->estimatedAmount);
        }

        // Transform dates if they exist.
        $date_fields = ['bidOpeningDate', 'constructionStartDate', 'preBidDate', 'substantialCompletionDate'];
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
   * Get building coordinators by building number.
   *
   * @return array
   *   The building coordinators object.
   */
  public function getBuildingCoordinators($building_number) {
    $data = $this->request('GET', 'bldgCoordinators');
    $contact = [];

    foreach ($data as $d) {
      if ($building_number === $d->buildingNumber) {
        $contact = $d;
      }
    }

    return $contact;
  }

}
