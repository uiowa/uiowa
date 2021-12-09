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
   *   The API path to use. Do not include the base URL.
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
   * @return array
   *   The session object.
   */
  public function getCurrentSession() {
    return $this->request('GET', '/pub/registrar/sessions/current');
  }

  /**
   * Find a list of sessions specified by how many previous and future.
   *
   * @param int $previous
   *   The number of session back from the current session.
   * @param int $future
   *   The number of session after the current session.
   *
   * @return array
   *   Array of session objects.
   */
  public function getSessionsBounded($previous = 4, $future = 4) {
    $data = $this->request('GET', '/pub/registrar/sessions/bounded', [
      'previous' => $previous,
      'future' => $future,
    ]);

    // Sort by start date.
    usort($data, function ($a, $b) {
      return strtotime($a->startDate) > strtotime($b->startDate);
    });

    return $data;
  }

  /**
   * Find a list of sessions specified by a query.
   *
   * GET /pub/registrar/sessions/range.
   *
   * @param int $from
   *   The internal id of the session.
   * @param int $steps
   *   The number of steps to take from the 'from' session. May be negative
   *        to go back. Cannot be zero.
   * @param string $term
   *   The session term enum value: SUMMER, FALL, WINTER or SPRING. This is
   *        case-sensitive but that is not documented in the API.
   *
   * @return array
   *   JSON decoded array of response data.
   */
  public function getSessionsRange($from, $steps, $term = NULL) {
    $data = $this->request('GET', '/pub/registrar/sessions/range', [
      'from' => $from,
      'steps' => $steps,
      'term' => strtoupper($term),
    ]);

    // Sort by start date.
    usort($data, function ($a, $b) {
      return strtotime($a->startDate) > strtotime($b->startDate);
    });

    return $data;
  }

  /**
   * Search session dates.
   *
   * GET /pub/registrar/session-dates.
   *
   * @param int $session_id
   *   The internal session id to search. Either this or sessionCode must
   *    be specified.
   * @param string $date_category
   *   The natural key of the date category you are interested in. (e.g.
   *    HOUSING_DINING).
   * @param mixed $print_date
   *   Whether or not to include Print Date dates in results. The API
   *    requires a string value so booleans are converted here.
   * @param string $five_year_date
   *   Whether or not to include Five Year Date dates in results.
   * @param int $session_code
   *   The session code to search (20148 for example).
   * @param string $date
   *   The natural key of the session date you are interested in. (e.g.
   *    ISISAVAIL).
   * @param string $context
   *   The natural key of the calendar context you are interested in. (e.g.
   *    DEFAULT).
   *
   * @return array
   *   JSON decoded array of response data.
   */
  public function searchSessionDates($session_id, $date_category = NULL, $print_date = NULL, $five_year_date = NULL, $session_code = NULL, $date = NULL, $context = NULL) {
    $data = $this->request('GET', '/pub/registrar/session-dates', [
      'context' => $context,
      'date' => $date,
      'sessionCode' => $session_code,
      'sessionId' => $session_id,
      'fiveYearDate' => is_bool($five_year_date) ? var_export($five_year_date, TRUE) : $five_year_date,
      'printDate' => is_bool($print_date) ? var_export($print_date, TRUE) : $print_date,
      'dateCategory' => $date_category,
    ]);

    // Filter out dates with no categories.
    $data = array_filter($data, function ($v) {
      return (!empty($v->dateCategoryLookups));
    });

    // Sort by start date and then subsession, if set.
    usort($data, function ($a, $b) {
      $a_key = strtotime($a->beginDate);
      $b_key = strtotime($b->beginDate);

      if (!empty($a->subSession)) {
        $a_key++;
      }

      if (!empty($b->subSession)) {
        $b_key++;
      }

      return $a_key <=> $b_key;
    });

    return $data;
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
