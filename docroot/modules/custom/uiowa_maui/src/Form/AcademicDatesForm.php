<?php

namespace Drupal\uiowa_maui\Form;

use Drupal\Component\Utility\Html;
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
    static $count;
    $count++;
    return 'uiowa_maui_academic_dates_' . $count;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $session_prefilter = NULL, $category_prefilter = NULL, $child_heading_size = NULL) {
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

    // This ID needs to be different than the form ID.
    $form['dates-wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => $wrapper,
        'aria-live' => 'polite',
      ],
      'dates' => [],
    ];

    $data = $this->maui->searchSessionDates($current, $category);

    if (!empty($data)) {
      $form['dates-wrapper']['dates'] = [
        '#theme' => 'uiowa_maui_session_dates',
        '#data' => $data,
        '#child_heading_size' => $child_heading_size,
      ];
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
