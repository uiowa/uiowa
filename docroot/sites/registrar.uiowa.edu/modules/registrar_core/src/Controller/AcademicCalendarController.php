<?php

namespace Drupal\registrar_core\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\RendererInterface;
use Drupal\registrar_core\SessionColorTrait;
use Drupal\uiowa_maui\MauiApi;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for the Academic Calendar.
 */
class AcademicCalendarController extends ControllerBase {
  use SessionColorTrait;

  /**
   * The MAUI API service.
   *
   * @var \Drupal\uiowa_maui\MauiApi
   */
  protected $maui;

  /**
   * The cache backend service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBackend;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs a new AcademicCalendarController.
   *
   * @param \Drupal\uiowa_maui\MauiApi $maui
   *   The MAUI API service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(MauiApi $maui, CacheBackendInterface $cache_backend, RendererInterface $renderer) {
    $this->maui = $maui;
    $this->cacheBackend = $cache_backend;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('uiowa_maui.api'),
      $container->get('cache.default'),
      $container->get('renderer')
    );
  }

  /**
   * Retrieves calendar data for both regular and five-year calendars.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param bool $isFiveYearCalendar
   *   Whether this is a request for the five-year calendar.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response containing the calendar data.
   */
  public function getCalendarData(Request $request, $isFiveYearCalendar = FALSE) {
    $start = $request->query->get('start');
    $end = $request->query->get('end');
    $category = $request->query->all()['category'] ?? [];
    $steps = $request->query->get('steps', 0);
    $includePastSessions = $request->query->get('includePastSessions', 0);

    // Ensure category is always an array.
    if (!is_array($category)) {
      $category = [$category];
    }

    $calendarType = $isFiveYearCalendar ? 'five_year_academic_calendar' : 'academic_calendar';
    $cid = "registrar_core:{$calendarType}:" . $start . ':' . $end . ':' . implode(',', $category);

    if (!$isFiveYearCalendar) {
      $cid .= ':1:' . $steps . ':' . $includePastSessions;
    }

    if ($cache = $this->cacheBackend->get($cid)) {
      $data = $cache->data;
    }
    else {
      $data = $this->fetchAndProcessCalendarData($start, $end, $category, $steps, $includePastSessions, $isFiveYearCalendar);
      // Cache for 24 hours.
      $this->cacheBackend->set($cid, $data, time() + 86400);
    }

    return new JsonResponse($data);
  }

  /**
   * Creates a weight encoded string from an event.
   *
   * Creates a string that encodes weight data in it so that an alphabetical
   *     check can sort it without doing additional work.
   *
   * @param object $event
   *   The event we will construct our sort string from.
   *
   * @return string
   *   A string with weight data encoded for sorting.
   */
  private function sortString(object $event): string {
    $title = $event->title;
    $titleWeight = 0;
    $isSubSession = $event->subSession;
    if ($isSubSession) {
      $subSession = explode(':', $title);
      $title = end($subSession);

      $weightLookup = [
        '' => 0,
        '4wk' => 2,
        '6wk I' => 4,
        '6wk II' => 6,
        '8wk' => 8,
        '12wk' => 10,
      ];
      $titleWeight += $weightLookup[$subSession[0]];
    }

    if ($titleWeight < 10) {
      $titleWeight = '0' . $titleWeight;
    }

    // Example sorting weight
    // Title - Observed - session.
    return $titleWeight . trim($title);
  }

  /**
   * Compares two events so we can sort them with a custom sorting pass.
   *
   * @param object $event1
   *   The first event to compare.
   * @param object $event2
   *   The second event to compare.
   *
   * @return int
   *   Less than 0 if $event1 should be placed before $event2,
   *   0 if they have no weight difference,
   *   And greater than 0 if $event1 should be placed after $event2.
   */
  private function eventCompare(object $event1, object $event2): int {

    // If both events have the same date, we need to do more sorting.
    // COMMENT HERE.
    if ($event1->start === $event2->start) {
      $sortString1 = $event1->sortString;
      $sortString2 = $event2->sortString;

      return strcasecmp($sortString1, $sortString2);
    }

    // If they are not the same date, return the comparison.
    else {
      return ($event1->start < $event2->start) ? -1 : 1;
    }
  }

  /**
   * Fetches and processes calendar data.
   *
   * @param string $start
   *   The start date.
   * @param string $end
   *   The end date.
   * @param array $categories
   *   The categories to filter by.
   * @param int $steps
   *   The number of sessions to fetch.
   * @param int $includePastSessions
   *   Whether to include an equivalent number of past sessions equal to $steps.
   * @param bool $isFiveYearCalendar
   *   Whether this is for the five-year calendar view. Defaults to FALSE.
   *
   * @return array
   *   The processed calendar data.
   */
  private function fetchAndProcessCalendarData($start, $end, $categories, $steps = 0, $includePastSessions = FALSE, $isFiveYearCalendar = FALSE) {
    $current = $this->maui->getCurrentSession();

    // For five-year calendar.
    if ($isFiveYearCalendar) {
      $steps = 20;
      $includePastSessions = TRUE;
    }

    $sessions = ((int) $steps === 0) ? [$current] : $this->maui->getSessionsRange($current->id, max(1, $steps));

    if ($includePastSessions) {
      // The getSessionsRange method includes the current session as the
      // last element. The $steps variable is exclusive of the
      // current session, so we use $steps + 1 to get the current
      // number of steps including the current session.
      // The current session is removed in the next step with array_merge.
      $pastSessions = array_slice($this->maui->getSessionsRange($current->id, -$steps - 1), -$steps - 1, $steps + 1);
      $sessions = array_merge($pastSessions, $sessions);
    }

    $events = [];

    foreach ($sessions as $session_index => $session) {
      $dates = $this->maui->searchSessionDates(
        $session->id, [
          'startDate' => $start,
          'endDate' => $end,
        ],
        TRUE,
        $isFiveYearCalendar ?: NULL
      );

      foreach ($dates as $date) {
        // Skip the reviewed check for five-year calendar.
        if (!$isFiveYearCalendar && $date->reviewed !== TRUE) {
          continue;
        }

        if (!empty($date->dateCategoryLookups)) {

          // Split any events that are multiple days
          // into multiple event entries.
          if ($date->endDate === $date->beginDate) {
            $events[] = $this->processDate($date, $session, $session_index, $session->legacyCode);
          }
          else {
            $events = [...$events, ...$this->splitMultiDayEvent($date, $session, $session_index, $session->legacyCode)];
          }
        }
      }
    }

    usort(
      $events,
      [
        'Drupal\registrar_core\Controller\AcademicCalendarController',
        'eventCompare',
      ]
    );

    return $events;
  }

  /**
   * Filters an event based on categories and subsession.
   *
   * @param object $event
   *   The event to filter.
   * @param array $categories
   *   The categories to filter by.
   * @param bool $subsession
   *   Whether to include subsessions.
   *
   * @return bool
   *   TRUE if the event should be included, FALSE otherwise.
   */
  private function filterEvent($event, $categories, $subsession) {
    $category_match = empty($categories) || array_intersect(array_keys($event->categories), $categories);
    $subsession_match = $subsession || !$event->subSession;
    return $category_match && $subsession_match;
  }

  /**
   * Splits a multi day event, so it can be displayed multiple times.
   *
   * @param object $date
   *   The date object to split into multiple events.
   * @param object $session
   *   The session object.
   * @param int $session_index
   *   The session index.
   *
   * @return Object[]
   *   TRUE if the event should be included, FALSE otherwise.
   */
  private function splitMultiDayEvent(object $date, object $session, int $session_index) : array {
    $days = [];
    $interval = new \DateInterval('P1D');
    $realEnd = new \DateTime($date->endDate);
    $realEnd->add($interval);
    $period = new \DatePeriod(
      new \DateTime($date->beginDate),
      $interval,
      $realEnd
    );
    foreach ($period as $day) {
      array_push($days, $day);
    }

    $newEvents = [];
    foreach ($days as $day) {
      $newEvent = $this->processDate($date, $session, $session_index, $session->legacyCode, $day);
      $newEvents[] = $newEvent;
    }

    return $newEvents;
  }

  /**
   * Processes a date into an event object.
   *
   * @param object $date
   *   The date object to process.
   * @param object $session
   *   The session object.
   * @param int $session_index
   *   The session index.
   *
   * @return object
   *   The processed event object.
   */

  /**
   * In the processDate() method of AcademicCalendarController.
   */
  private function processDate($date, $session, $session_index, $session_legacy_id, $alt_start_day = NULL) {
    $event = new \stdClass();
    $event->title = Xss::filterAdmin($date->dateLookup->description);
    $event->start = $date->beginDate;
    $event->end = date('Y-m-d', strtotime($date->endDate . ' +1 day'));

    $event->categories = [];

    // Add context to title of event if context is not default.
    if (Xss::filterAdmin($date->contextLookup->naturalKey != 'DEFAULT')) {
      $event->title = $event->title . " - " . Xss::filterAdmin($date->contextLookup->description);
    }

    // Determine the date to display.
    $original_start_timestamp = strtotime($date->beginDate);
    $start_timestamp = strtotime($date->beginDate);

    if ($alt_start_day) {
      $event->start = $alt_start_day->format('Y-m-d\TH:i:s.000+0000');
      $start_timestamp = strtotime($event->start);
    }

    $start = date('D, M j, Y', $start_timestamp);
    $month = date('M', $start_timestamp);
    $day = date('j', $start_timestamp);

    // We want to show the original start in the subtitle.
    if ($alt_start_day && $date->endDate !== $date->beginDate) {
      $formatted_start = date('D, M j, Y', $original_start_timestamp);
      $end = date('D, M j, Y', strtotime($date->endDate));
      $start = "{$formatted_start} - {$end}";
    }

    $event->displayDate = $start;

    // Determine what session to display.
    if (isset($date->subSession)) {
      $session_display = $date->subSession;
      $event->subSession = TRUE;
      $parts = explode('-', $date->subSession);
      $prefix = trim($parts[1]);
      $prefix = str_replace(' Week', 'wk', $prefix);
      $event->title = "{$prefix}: {$event->title}";
    }
    else {
      $session_display = $session->shortDescription;
      $event->subSession = FALSE;
    }

    $event->sessionDisplay = $session_display;
    $event->sessionId = $session_legacy_id;

    $bg_color = $this->getSessionColor($session_index);
    $event->bgColor = $bg_color;

    $event->className = [
      'uiowa-maui-fc-event',
      'badge',
      'badge--' . $bg_color,
      Html::getClass($session_display),
    ];

    // Add dateCategoryLookups for filtering.
    foreach ($date->dateCategoryLookups as $category) {
      $event->categories[$category->naturalKey] = $category->description;
    }

    // Add description.
    $event->description = Xss::filterAdmin($date->dateLookup->webDescription ?? '');

    // Build card.
    $attributes = [];
    $attributes['class'] = [
      'card--layout-left',
      'borderless',
      'click-container',
      'block--word-break',
      'card',
      'media--default',
      'media--no-crop',
      'media',
    ];
    $card = [
      '#type' => 'card',
      '#title' => html_entity_decode($event->title),
      '#title_heading_size' => 'h3',
      '#attributes' => $attributes,
      '#media' => $this->t('
<div class="media--date"><span class="media--date__month">
@month</span><span class="media--date__day">
@day</span></div>',
        ['@month' => $month, '@day' => $day]),
      '#subtitle' => [
        'date' => [
          '#type' => 'markup',
          '#markup' => $start,
        ],
      ],
      '#meta' => [
        'description' => [
          '#type' => 'markup',
          '#markup' => $event->description,
        ],

      ],
      '#content' => [
        'body' => [
          '#type' => 'markup',
          '#markup' => '<span class="' . implode(' ', $event->className) . '">' . $event->sessionDisplay . '</span>',
        ],
      ],
    ];
    $event->rendered = $this->renderer->render($card);

    $event->sortString = $this->sortString($event);

    return $event;
  }

}
