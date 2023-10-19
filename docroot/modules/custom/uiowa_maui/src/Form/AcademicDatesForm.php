<?php

namespace Drupal\uiowa_maui\Form;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\uiowa_maui\MauiApi;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a uiowa_maui form.
 */
class AcademicDatesForm extends FormBase {
  /**
   * The MAUI API service.
   */
  protected MauiApi $maui;

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
    static $count;
    $count++;
    return 'uiowa_maui_academic_dates_' . $count;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $session_prefilter = NULL, $category_prefilter = NULL, $child_heading_size = NULL, $items_to_display = NULL, $limit_dates = FALSE) {
    $current = $form_state->getValue('session') ?? $session_prefilter ?? $this->maui->getCurrentSession()->id;
    $category = $form_state->getValue('category') ?? $category_prefilter;

    $wrapper = Html::getUniqueId('uiowa-maui-dates-wrapper');

    if ($session_prefilter === NULL) {
      $options = [];

      foreach ($this->maui->getSessionsBounded() as $session) {
        $options[$session->id] = Html::escape($session->shortDescription);
      }

      $form['session'] = [
        '#type' => 'select',
        '#title' => $this->t('Session'),
        '#description' => $this->t('Select a session to filter dates on.'),
        '#default_value' => $current,
        '#options' => $options,
        '#ajax' => [
          'callback' => [$this, 'sessionChanged'],
          'wrapper' => $wrapper,
          'method' => 'html',
          'disable-refocus' => TRUE,
        ],
      ];
    }
    else {
      // Get the relative session from the prefilter value.
      $bounding = $this->maui->getSessionsBounded(0, 3);
      $current = $bounding[$session_prefilter]->id;
    }

    if ($category_prefilter === NULL) {
      $form['category'] = [
        '#type' => 'select',
        '#title' => $this->t('Category'),
        '#description' => $this->t('Select a category to filter dates on.'),
        '#default_value' => $category,
        '#empty_value' => NULL,
        '#empty_option' => $this->t('- All -'),
        '#options' => $this->maui->getDateCategories(),
        '#ajax' => [
          'callback' => [$this, 'categoryChanged'],
          'wrapper' => $wrapper,
          'method' => 'html',
          'disable-refocus' => TRUE,
        ],
      ];
    }

    // This ID needs to be different from the form ID.
    $form['dates-wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => $wrapper,
        'aria-live' => 'polite',
        'class' => 'list-container__inner',
      ],
    ];

    $data = $this->maui->searchSessionDates($current, $category);

    if (!empty($data)) {
      $data = ((int) $limit_dates === 1) ? array_slice($data, 0, $items_to_display, TRUE) : $data;

      foreach ($data as $date) {
        $start = strtotime($date->beginDate);
        $end = strtotime($date->endDate);
        $key = $start . $end;

        $attributes = [];
        $attributes['class'] = [
          'borderless',
          'headline--serif',
        ];

        // Web description is not always set.
        // The subsession takes priority if set.
        $subsession = $date->subSession ?? FALSE;

        $item = [
          '#type' => 'container',
          '#attributes' => [
            'class' => 'session',
          ],
        ];
        $item['description'] = [
          '#type' => 'markup',
          '#markup' => Xss::filter($date->dateLookup->webDescription ?? $date->dateLookup->description),
        ];
        $item['session_badge'] = [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => Xss::filter($subsession ?: $date->session->shortDescription),
          '#attributes' => [
            'class' => $subsession ? 'badge badge--primary subsession' : 'badge badge--primary session',
          ],
        ];

        // Group items by date.
        if (isset($form['dates-wrapper']['dates'][$key])) {
          $form['dates-wrapper']['dates'][$key]['#subtitle'][] = $item;
        }
        else {
          $form['dates-wrapper']['dates'][$key] = [
            '#type' => 'card',
            '#attributes' => $attributes,
            '#title' => $this->t('@start@end', [
              '@start' => date('F j, Y', $start),
              '@end' => $end === $start ? '' : ' - ' . date('F j, Y', $end),
            ]),
            '#subtitle' => [$item],
          ];
        }
      }
    }
    else {
      $form['dates-wrapper']['dates'] = [
        'none' => [
          '#type' => 'container',
          '#attributes' => [
            'class' => 'uiowa-maui-no-results',
          ],
          '#markup' => $this->t('No dates found.'),
        ],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // No-op.
  }

  /**
   * AJAX callback for session form element change.
   */
  public function sessionChanged(array &$form, FormStateInterface $form_state) {
    return $form['dates-wrapper']['dates'];
  }

  /**
   * AJAX callback for category form element change.
   */
  public function categoryChanged(array &$form, FormStateInterface $form_state) {
    return $form['dates-wrapper']['dates'];
  }

}
