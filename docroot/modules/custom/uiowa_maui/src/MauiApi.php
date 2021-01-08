<?php

namespace Drupal\uiowa_maui;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\CacheBackendInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;

/**
 * Maui API service.
 *
 * @see: https://api.maui.uiowa.edu/maui/pub/webservices/documentation.page
 */
class MauiApi {

  const BASE = 'https://api.maui.uiowa.edu/maui/api/';

  /**
   * The uiowa_maui logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The uiowa_maui cache.
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
   * Constructs a Maui object.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The uiowa_maui logger channel.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The uiowa_maui cache.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   */
  public function __construct(LoggerInterface $logger, CacheBackendInterface $cache, ClientInterface $http_client) {
    $this->logger = $logger;
    $this->cache = $cache;
    $this->client = $http_client;
  }

  /**
   * Make a MAUI API request and return data.
   *
   * @param string $method
   *   The HTTP method to use.
   * @param string $path
   *   The API path to use. Do no include the base URL.
   * @param array $params
   *   Optional request parameters.
   * @param array $options
   *   Optional request options. All requests expect JSON response data.
   *
   * @return mixed
   *   The API response data.
   */
  public function request($method, $path, array $params = [], array $options = []) {
    // Encode any special characters and trim duplicate slash.
    $path = UrlHelper::encodePath($path);
    $uri = self::BASE . ltrim($path, '/');

    // Append any query string parameters.
    if (!empty($params)) {
      $query = UrlHelper::buildQuery($params);
      $uri .= "?{$query}";
    }

    // Merge additional options with default.
    $options = array_merge($options, [
      'headers' => [
        'Content-type' => 'application/json',
      ],
    ]);

    // Create a hash for the CID. Can always be decoded for debugging purposes.
    $hash = base64_encode($uri . serialize($options));
    $cid = "uiowa_maui:request:{$hash}";
    $data = [];

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
   * Get the current session and return the session object.
   *
   * @return object
   *   The session object.
   */
  public function getCurrentSession() {
    $data = $this->request('GET', '/pub/registrar/sessions/current');
    return new MauiSession($data);
  }

  /**
   * Return a static list of session date categories as key/value pairs.
   *
   * There is no API call to get these right now. The array keys are the
   * machine names of the categories and the value is the human-readable name.
   *
   * @return array
   *   List of session date categories.
   */
  public function getDateCategories() {
    return [
      'Student' => [
        'STUDENT' => 'All Student Dates',
        'DISTANCE_ONLINE_ED' => 'Distance and Online Education',
        'GRADUATE_STUDENTS' => 'Graduate Students',
        'GRADUATION_COMMENCEMENT' => 'Graduation and Commencement',
        'HOUSING_DINING' => 'Housing and Dining',
        'NO_CLASSES' => 'No Classes',
        'STUDENT_REGISTRATION' => 'Registration Dates and Deadlines',
      ],
      'Faculty/Staff' => [
        'OFFERINGS_CLASSROOMS' => 'Course Offerings / Classroom Scheduling',
        'DEPARTMENTAL_ADMIN' => 'Departmental Admin',
        'GRADES_ATTENDANCE' => 'Grades and Attendance',
        'REGISTRATION_ADMIN' => 'Registration Admin',
        'UNIVERSITY_OFFICES_CLOSED' => 'University Offices Closed',
      ],
    ];
  }

}
