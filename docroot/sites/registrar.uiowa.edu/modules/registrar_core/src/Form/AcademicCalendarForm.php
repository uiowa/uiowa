<?php

namespace Drupal\registrar_core\Form;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\uiowa_maui\MauiApi;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for the Academic Calendar.
 */
class AcademicCalendarForm extends FormBase {
  /**
   * The MAUI API service.
   *
   * @var \Drupal\uiowa_maui\MauiApi
   */
  protected $maui;

  /**
   * DatesBySessionForm constructor.
   *
   * @param \Drupal\uiowa_maui\MauiApi $maui
   *   The MAUI API service.
   */
  public function __construct(MauiApi $maui) {
    $this->maui = $maui;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('uiowa_maui.api')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'uiowa_academic_calendar_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $steps = 0) {
    $events = $keys = $categories = [];
    $current = $this->maui->getCurrentSession();
    $sessions = $this->maui->getSessionsRange($current->id, max(1, $steps));
    $last = end($sessions);

    $i = 1;
    $bgs = [
      1 => 'primary',
      2 => 'success',
      3 => 'info',
      4 => 'warning',
      5 => 'danger',
    ];

    foreach ($sessions as $session) {
      $dates = $this->maui->searchSessionDates($session->id);

      $keys[] = [
        '#type' => 'html_tag',
        '#tag' => 'li',
        '#value' => $session->shortDescription,
        '#attributes' => [
          'class' => [
            'uiowa-maui-key',
            'uiowa-maui-key-' . $i,
            'label',
            'label-' . $bgs[$i],
            $this->formatHtmlClass($session->shortDescription),
          ],
        ],
      ];

      foreach ($dates as $date) {
        if (!empty($date->dateCategoryLookups)) {
          $event = new \stdClass();
          $event->title = $this->filterXss($date->dateLookup->description);
          $event->start = $date->beginDate;
          $event->end = $date->endDate;
          $event->url = '#';
          $event->categories = [];

          // Determine the date to display in the popover.
          $start = date('M j, Y', strtotime($date->beginDate));

          if ($date->endDate != $date->beginDate) {
            $end = date('M j, Y', strtotime($date->endDate));
            $start = "{$start} - {$end}";
          }

          // Determine what session to display.
          if (isset($date->subSession)) {
            $session = $date->subSession;
            $event->subSession = TRUE;
            $parts = explode('-', $date->subSession);
            $prefix = trim($parts[1]);
            $prefix = str_replace(' Week', 'wk', $prefix);
            $event->title = "{$prefix}: {$event->title}";
          }
          else {
            $session = $date->session->shortDescription;
            $event->subSession = FALSE;
          }

          $event->popoverTitle = $this->filterXss($date->dateLookup->description);

          $event->className = [
            'uiowa-maui-fc-event',
            'label',
            'label-' . $bgs[$i],
            $this->formatHtmlClass($session),
          ];

          // Add dateCategoryLookups for filtering.
          foreach ($date->dateCategoryLookups as $category) {
            $event->categories[$category->naturalKey] = $category->description;
            $categories[$category->naturalKey] = $category->description;
          }

          // Prepare the popover content.
          $description = $this->filterXss($date->dateLookup->webDescription ?? NULL);

          $event->popoverContent = <<<EOD
<div class="uiowa-maui-fc-date">{$start}</div>
<div class="uiowa-maui-fc-description">{$description}</div>
<div class="label label-{$bgs[$i]} uiowa-maui-fc-session">{$session}</div>
EOD;

          // Append event.
          $events[] = $event;
        }
      }

      $i++;
    }

    // Construct the render array.
    $form = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'uiowa-maui-academic-calendar',
        ],
      ],
      '#attached' => [
        'library' => [
          'registrar_core/academic-calendar',
          'uids_base/view-calendar',
        ],
        'drupalSettings' => [
          'uiowaMaui' => [
            'calendarDates' => $events,
            'currentSession' => $current,
            'lastSession' => $last,
          ],
        ],
      ],
      'legend' => [
        '#theme' => 'item_list',
        '#attributes' => [
          'class' => [
            'uiowa-maui-legend',
          ],
        ],
        '#items' => $keys,
        '#title' => $this->t('Legend'),
      ],
      'filters' => [
        'category' => [
          '#type' => 'select',
          '#title' => $this->t('Category'),
          '#description' => $this->t('Select a category to filter dates on.'),
          '#options' => uiowa_maui_category_options(),
          '#default_value' => $this->getRequest()->query->get('category', 'STUDENT'),
          '#multiple' => TRUE,
        ],
        'subsession' => [
          '#type' => 'checkbox',
          '#title' => $this->t('Show subsessions'),
          '#description' => $this->t('Check to show subsessions.'),
          '#default_value' => $this->getRequest()->query->get('subsession', FALSE),
        ],
      ],
      'calendar' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'uiowa-maui-fullcalendar',
          ],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Handle form submission if needed.
  }

  /**
   * Formats a string into an HTML class.
   *
   * @param string $string
   *   The string to format.
   *
   * @return string
   *   The formatted HTML class.
   */
  protected function formatHtmlClass($string) {
    return \Drupal::service('transliteration')->transliterate($string, 'en', '_', 100);
  }

  /**
   * Filters HTML to prevent XSS attacks.
   *
   * @param string|null $string
   *   The string to filter.
   *
   * @return string|null
   *   The filtered string, or null if the input was null.
   */
  protected function filterXss($string) {
    if ($string !== NULL) {
      return Xss::filter($string);
    }
    else {
      return NULL;
    }
  }

}
