<?php

namespace Drupal\registrar_core\Form;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\sitenow_dispatch\DispatchApiClientInterface;
use Drupal\uiowa_maui\MauiApi;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Form for the final exams schedule block.
 */
class FinalExamScheduleForm extends FormBase {
  /**
   * The MAUI API service.
   *
   * @var \Drupal\uiowa_maui\MauiApi
   */
  protected $maui;

  /**
   * Constructs the SettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\uiowa_maui\MauiApi $maui
   *   The MAUI API service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, MauiApi $maui) {
    $this->configFactory = $config_factory;
    $this->maui = $maui;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('uiowa_maui.api'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'final_exam_schedule_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $wrapper_id = $this->getFormId() . '-wrapper';
    $form['#prefix'] = '<div id="' . $wrapper_id . '" aria-live="polite">';
    $form['#suffix'] = '</div>';

    $form['#id'] = 'correspondence-form';

    $form['final_exam'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'final-exam-schedule',
      ],
    ];

    $search = $form_state->getValue('search') ?? '';
    $form['final_exam']['search'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search'),
      '#default_value' => $search,
      '#description' => $this->t('The string to search against.'),
      '#prefix' => '<div id="final-exam-schedule-search" aria-controls="final-exam-schedule-content">',
      '#suffix' => '</div>',
    ];

    $form['final_exam']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#ajax' => [
        'callback' => [$this, 'ajaxCallback'],
        'wrapper' => 'final-exam-schedule-content',
      ],
    ];

    $form['final_exam']['session_id'] = [
      '#type' => 'hidden',
      '#value' => $form_state->getValue('session_id') ?? '',
    ];

    $headers = [
      'Sections',
      'Course Title',
      'Start Times',
      'End Times',
      'Rooms',
    ];

    $session = $form_state->getValue('session_id');
    if (empty($session)) {
      $session = '20243';
    }
    $data = $this->maui->getFinalExamSchedule($session);
    if (empty($data) || !isset($data['NewDataSet']['Table'])) {
      // @todo Add some handling if data fetching failed
      //   or there's something weird with the structure.
      $data = [];
    }
    else {
      $data = $data['NewDataSet']['Table'];
    }
    $allowed_tags = [
      'a',
      'strong',
      'em',
      'br',
    ];

    foreach ($data as $index => $row) {
      $pass = FALSE;
      $new_row = [];
      foreach (['sections',
        'course_title',
        'start_time',
        'end_time',
        'rooms',
      ] as $key) {
        $value = Markup::create(Xss::filter($row[$key], $allowed_tags));
        if (!$pass && str_contains($value, $search)) {
          $pass = TRUE;
        }
        $new_row[] = $value;
      }
      if ($pass) {
        $data[$index] = $new_row;
      }
      else {
        unset($data[$index]);
      }
    }

    usort($data, function ($a, $b) {
      return $a[0] <=> $b[0];
    });

    $form['final_exam']['content'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'final-exam-schedule-content',
        'class' => 'element--margin__top--extra',
        'role' => 'region',
        'aria-live' => 'polite',
      ],
    ];

    $form['final_exam']['content']['table'] = [
      '#theme' => 'table',
      '#header' => $headers,
      '#rows' => $data,
      '#prefix' => '<div id="final-exam-schedule-content-table">',
      '#suffix' => '</div>',
    ];

    return $form;

  }

  /**
   * AJAX callback for the form.
   */
  public function ajaxCallback(array &$form, FormStateInterface $form_state) {
    return $form['final_exam']['content'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // No-op.
  }

}
