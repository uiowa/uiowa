<?php

namespace Drupal\uiowa_maui;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\uiowa_core\ApiClientBase;

/**
 * Maui API service.
 *
 * @see: https://api.maui.uiowa.edu/maui/pub/webservices/documentation.page
 */
class MauiApi extends ApiClientBase {
  use SessionTermTrait;

  /**
   * {@inheritdoc}
   */
  public function basePath(): string {
    return 'https://api.maui.uiowa.edu/maui/api/';
  }

  /**
   * {@inheritdoc}
   */
  protected function getCacheIdBase(): string {
    return 'uiowa_maui';
  }

  /**
   * Get the current session and return the session object.
   *
   * @return array
   *   The session object.
   */
  public function getCurrentSession() {
    return $this->get('/pub/registrar/sessions/current');
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
    $data = $this->get('/pub/registrar/sessions/bounded', [
      'query' => [
        'previous' => $previous,
        'future' => $future,
      ],
    ]);

    // Sort by start date.
    usort($data, function ($a, $b): int {
      return strtotime($a->startDate) <=> strtotime($b->startDate);
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
    $data = $this->get('/pub/registrar/sessions/range', [
      'query' => [
        'from' => $from,
        'steps' => $steps,
        'term' => $term !== NULL ? strtoupper($term) : NULL,
      ],
    ]);

    // Sort by start date.
    usort($data, function ($a, $b) {
      return strtotime($a->startDate) <=> strtotime($b->startDate);
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
    $data = $this->get('/pub/registrar/session-dates', [
      'query' => [
        'context' => $context,
        'date' => $date,
        'sessionCode' => $session_code,
        'sessionId' => $session_id,
        'fiveYearDate' => is_bool($five_year_date) ? var_export($five_year_date, TRUE) : $five_year_date,
        'printDate' => is_bool($print_date) ? var_export($print_date, TRUE) : $print_date,
        'dateCategory' => $date_category,
      ],
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

  /**
   * Get room data based on building ID and room ID.
   *
   * @param string $building_id
   *   The building id of the room.
   * @param string $room_id
   *   The room id of the room.
   *
   * @return mixed
   *   The API response data.
   */
  public function getRoomData($building_id, $room_id) {
    return $this->get("/pub/registrar/courses/AstraRoomData/{$building_id}/{$room_id}");
  }

  /**
   * Return the schedule for a classroom for a date range.
   *
   * GET /pub/registrar/courses/AstraRoomSchedule/{startDate}/{endDate}/{bldgCode}/{roomNumber}.
   *
   * @param string $startdate
   *   Date formated as YYYY-MM-DD.
   * @param string $enddate
   *   Date formated as YYYY-MM-DD.
   * @param string $building_id
   *   The building code needs to match the code as it is entered in Astra.
   * @param string $room_id
   *   The room number needs to match the code as it is entered in Astra.
   *
   * @return array
   *   JSON decoded array of response data.
   */
  public function getRoomSchedule($startdate, $enddate, $building_id, $room_id) {
    return $this->get("/pub/registrar/courses/AstraRoomSchedule/{$startdate}/{$enddate}/{$building_id}/{$room_id}");
  }

  /**
   * Get complete building list.
   *
   * @return mixed
   *   The API response data.
   */
  public function getClassroomsData($room_category = 'UNIVERSITY_CLASSROOM') {
    return $this->get('/pub/facilityBuildingRoom/list', [
      'query' => [
        'roomCategory' => $room_category,
      ],
    ]);
  }

  /**
   * The section/course search web service by internal id.
   *
   * GET /pub/registrar/sections/{sectionId: /d+}.
   *
   * @param string $section
   *   The section id.
   * @param array $exclude
   *   The exclusion parameters.
   *
   * @return array
   *   JSON decoded array of response data.
   */
  public function getSection($section, $exclude) {
    return $this->get("pub/registrar/sections/{$section}", [
      'query' => [
        'exclude' => json_encode($exclude),
        ]
    ]);
  }

  /**
   * Find all course subjects.
   *
   * GET /pub/lookups/registrar/coursesubjects.
   *
   * @return array
   *   JSON decoded array of response data.
   */
  public function getCourseSubjects() {
    $data = $this->get('pub/lookups/registrar/coursesubjects');

    if ($data) {
      // Sort alphabetically by natural key, i.e. CHEM.
      usort($data, function ($a, $b) {
        return strcasecmp($a->naturalKey, $b->naturalKey);
      });
    }

    return $data;
  }

  /**
   * Get year options for the start year select.
   *
   * @param int $previous
   *   Number of years to go back.
   * @param int $future
   *   Number of years to go forward.
   *
   * @return array
   *   Array of year options.
   */
  public function getYearOptions($previous = 4, $future = 10) {
    $currentSession = $this->getCurrentSession();
    $startDate = (new DrupalDateTime($currentSession->startDate))
      ->modify("-{$previous} years")
      ->format('Y-m-d');

    $start = $this->get('/pub/registrar/sessions/by-date', [
      'query' => [
        'date' => $startDate,
      ],
    ]);
    $range = $this->getSessionsRange($start->id, $previous + $future, 'FALL');

    $options = [];

    foreach ($range as $session) {
      $startYear = date('Y', strtotime($session->startDate));
      $endYear = substr((string) ($startYear + 1), -2);
      $academicYear = "{$startYear}-{$endYear}";

      // Use the academic year as the key to avoid duplicates.
      if (!isset($options[$academicYear])) {
        $options[$academicYear] = $session->id;
      }
    }

    // Flip to have session IDs as keys and academic years as values.
    return array_flip($options);
  }

  /**
   * Basic final exam fetcher.
   */
  public function getFinalExamSchedule($session_id) {
    $endpoint = "pub/registrar/exam-schedule/{$session_id}";
    $options = [
      'headers' => [
        'Accept' => 'application/xml',
        'Content-Type' => 'application/x-www-form-urlencoded',
      ],
    ];
    $data = $this->get($endpoint, $options, 'xml');
    return $data;
  }

  /**
   * Get identical, cross-referenced courses.
   *
   * @return mixed
   *   The API response data.
   */
  public function getIdenticalCourses($session, $courseId): mixed {
    $data = $this->get("pub/registrar/course/search", [
      'query' => [
        'session' => $session,
        'courseId' => $courseId,
      ],
    ]);
    return $data->payload[0]->identities;
  }

}
