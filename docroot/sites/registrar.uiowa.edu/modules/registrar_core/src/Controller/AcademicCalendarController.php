<?php

namespace Drupal\registrar_core\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Controller\ControllerBase;
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
   * Constructs a new AcademicCalendarController.
   *
   * @param \Drupal\uiowa_maui\MauiApi $maui
   *   The MAUI API service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend service.
   */
  public function __construct(MauiApi $maui, CacheBackendInterface $cache_backend) {
    $this->maui = $maui;
    $this->cacheBackend = $cache_backend;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('uiowa_maui.api'),
      $container->get('cache.default')
    );
  }

  /**
   * Retrieves calendar data.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response containing the calendar data.
   */
  public function getCalendarData(Request $request) {
    $start = $request->query->get('start');
    $end = $request->query->get('end');
    $category = $request->query->all()['category'] ?? [];
    $subsession = $request->query->get('subsession', '0');
    $steps = $request->query->get('steps', 0);

    // Ensure category is always an array.
    if (!is_array($category)) {
      $category = [$category];
    }

    // Convert subsession to boolean.
    $subsession = filter_var($subsession, FILTER_VALIDATE_BOOLEAN);

    $cid = 'registrar_core:academic_calendar:' . $start . ':' . $end . ':' . implode(',', $category) . ':' . ($subsession ? '1' : '0') . ':' . $steps;
    $data = NULL;

    if ($cache = $this->cacheBackend->get($cid)) {
      $data = $cache->data;
    }
    else {
      $data = $this->fetchAndProcessCalendarData($start, $end, $category, $subsession, $steps);
      // Cache for 24 hours.
      $this->cacheBackend->set($cid, $data, time() + 86400);
    }

    return new JsonResponse($data);
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
   * @param bool $subsession
   *   Whether to include subsessions.
   * @param int $steps
   *   The number of sessions to fetch.
   *
   * @return array
   *   The processed calendar data.
   */
  private function fetchAndProcessCalendarData($start, $end, $categories, $subsession, $steps) {
    $current = $this->maui->getCurrentSession();
    $sessions = $this->maui->getSessionsRange($current->id, max(1, $steps));

    $events = [];

    foreach ($sessions as $session_index => $session) {
      $dates = $this->maui->searchSessionDates($session->id);
      foreach ($dates as $date) {
        if (!empty($date->dateCategoryLookups) &&
          (!$start || $date->beginDate >= $start) &&
          (!$end || $date->endDate <= $end)) {
          $event = $this->processDate($date, $session, $session_index);
          if ($this->filterEvent($event, $categories, $subsession)) {
            $events[] = $event;
          }
        }
      }
    }

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
  private function processDate($date, $session, $session_index) {
    $event = new \stdClass();
    $event->title = Xss::filterAdmin($date->dateLookup->description);
    $event->start = $date->beginDate;
    // Adjust the end date.
    $event->end = date('Y-m-d', strtotime($date->endDate . ' +1 day'));

    $event->categories = [];

    // Determine the date to display in the popover.
    $start = date('M j, Y', strtotime($date->beginDate));

    if ($date->endDate != $date->beginDate) {
      $end = date('M j, Y', strtotime($date->endDate));
      $start = "{$start} - {$end}";
    }

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

    $event->popoverTitle = Xss::filterAdmin($date->dateLookup->description);

    $bg_color = $this->getSessionColor($session_index);

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

    // Prepare the popover content.
    $description = Xss::filterAdmin($date->dateLookup->webDescription ?? '');

    $event->popoverContent = <<<EOD
<div class="uiowa-maui-fc-date">{$start}</div>
<div class="uiowa-maui-fc-description">{$description}</div>
<div class="badge badge--{$bg_color} uiowa-maui-fc-session">{$session_display}</div>
EOD;

    return $event;
  }

}
