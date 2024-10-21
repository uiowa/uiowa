<?php

namespace Drupal\registrar_core\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\uiowa_maui\MauiApi;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for department codes block.
 */
class DepartmentCodesForm extends FormBase {

  /**
   * The MAUI API service.
   *
   * @var \Drupal\uiowa_maui\MauiApi
   */
  protected $maui;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * CourseSubjectsTable constructor.
   *
   * @param \Drupal\uiowa_maui\MauiApi $maui
   *   The Maui API service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(MauiApi $maui, MessengerInterface $messenger) {
    $this->maui = $maui;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('uiowa_maui.api'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'course_subjects_table';
  }

  /**
   * {@inheritdoc}
   */

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $search = $form_state->getValue('search') ?? '';
    $rows = $this->getCourseSubjectRows($search);

    $form['search_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['uiowa-search-form']],
      '#prefix' => '<div id="final-exam-schedule-search" aria-controls="final-exam-schedule-content">',
      '#suffix' => '</div>',
    ];

    if (empty($rows) && !empty($search)) {
      $form['no_results'] = [
        '#type' => 'markup',
        '#markup' => '<p>' . $this->t('No results found for "@search".', ['@search' => $search]) . '</p>',
        '#prefix' => '<div class="no-results-message">',
        '#suffix' => '</div>',
      ];
    }

    $form['search_wrapper']['search'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search'),
      '#title_display' => 'invisible',
      '#title_attributes' => ['class' => ['element-invisible']],
      '#placeholder' => $this->t('Search'),
    ];

    $form['search_wrapper']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#prefix' => '<div class="form-item">',
      '#suffix' => '</div>',
    ];

    if (!empty($search)) {
      $form['search_wrapper']['reset'] = [
        '#type' => 'submit',
        '#value' => $this->t('Reset'),
        '#submit' => ['::resetForm'],
        '#limit_validation_errors' => [],
        '#attributes' => [
          'class' => ['bttn--secondary'],
        ],
        '#prefix' => '<div class="form-item">',
        '#suffix' => '</div>',
      ];
    }

    if (!empty($rows)) {
      $form['table'] = [
        '#type' => 'table',
        '#caption' => 'Department codes',
        '#attributes' => ['class' => ['table--hover-highlight table--gray-borders']],
        '#prefix' => '<div class="table-responsive">',
        '#suffix' => '</div>',
        '#header' => [
          [
            'data' => $this->t('Subject'),
            'scope' => 'col',
          ],
          [
            'data' => $this->t('Legacy Code'),
            'scope' => 'col',
          ],
          [
            'data' => $this->t('Description'),
            'scope' => 'col',
          ],
          [
            'data' => $this->t('Status'),
            'scope' => 'col',
          ],
        ],
        '#rows' => $rows,
      ];
    }

    return $form;
  }

  /**
   * Retrieves the course subject data and formats it for the table.
   *
   * @param string $search
   *   The search term to filter the results.
   *
   * @return array
   *   The table rows.
   */
  protected function getCourseSubjectRows($search = '') {
    $rows = [];
    $course_subjects = $this->maui->getCourseSubjects();

    foreach ($course_subjects as $subject) {
      $additionalInfo = (array) $subject->additionalInfo;
      $row = [
        'naturalKey' => $subject->naturalKey,
        'itchCode' => $additionalInfo['itchCode'] ?? '',
        'description' => $subject->description,
        'status' => $subject->status->label,
      ];

      if (empty($search) || $this->searchMatch($row, $search)) {
        $rows[] = $row;
      }
    }

    return $rows;
  }

  /**
   * Checks if a row matches the search term.
   *
   * @param array $row
   *   The row data.
   * @param string $search
   *   The search term.
   *
   * @return bool
   *   TRUE if the row matches the search, FALSE otherwise.
   */
  protected function searchMatch(array $row, $search) {
    $search = strtolower($search);
    foreach ($row as $value) {
      if (stripos($value, $search) !== FALSE) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild();
  }

  /**
   * Submit handler for the reset button.
   */
  public function resetForm(array &$form, FormStateInterface $form_state) {
    $form_state->setUserInput(['search'], '');
    $form_state->setRebuild(TRUE);
  }

}
