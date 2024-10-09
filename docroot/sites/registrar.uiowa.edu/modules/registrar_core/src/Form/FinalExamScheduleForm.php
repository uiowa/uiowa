<?php

namespace Drupal\registrar_core\Form;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\uiowa_maui\MauiApi;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
      '#description' => $this->t('You can search using any of the following methods:

Any part of a Subject:Course:Section number (e.g. ACCT:, or MATH:1005)
Any word in a course title (e.g. Technology)
A room (e.g. 205 NH)
Search results yield character string matches from all columns (e.g. a search for "math" displays any courses with a subject of MATH, course titles with the word math or any word containing the letters "math" like mathematics or aftermath.)'
      ),

      '#prefix' => '<div id="final-exam-schedule-search" aria-controls="final-exam-schedule-content">',
      '#suffix' => '</div>',
    ];

    $form['final_exam']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    $form['final_exam']['session_id'] = [
      '#type' => 'hidden',
      '#value' => $form_state->getValue('session_id') ?? '',
    ];

    $headers = [
      'Sections',
      'Course Title',
      'Exam Date and Time',
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
      // If we have an empty string, everything should pass.
      $pass = empty($string);
      $new_row = [];
      foreach (['sections',
        'course_title',
        'time',
        'rooms',
      ] as $key) {
        switch ($key) {
          case 'time':
            $start_time = strtotime($row['start_time']);
            $end_time = strtotime($row['end_time']);
            $start_formatted = date('D n/j/Y, g:iA', $start_time);
            $end_formatted = date('g:iA', $end_time);
            $new_row[] = "{$start_formatted} - {$end_formatted}";
            break;

          default:
            $value = Markup::create(Xss::filter($row[$key], $allowed_tags));
            // Str_contains is case-sensitive, so lowercase before comparing,
            // only if we haven't already matched.
            if (!$pass) {
              $lc_value = strtolower($value);
              $lc_search = strtolower($search);
              $pass = str_contains($lc_value, $lc_search);
            }
            $new_row[] = $value;
            break;
        }
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
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // No-op.
    $form_state->setRebuild(TRUE);
  }

}
