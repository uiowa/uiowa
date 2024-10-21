<?php

namespace Drupal\registrar_core\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
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
   * HoursFilterForm constructor.
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
    return 'course_subjects_table';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Subject'),
        $this->t('Legacy Code'),
        $this->t('Description'),
      ],
      '#rows' => $this->getCourseSubjectRows(),
    ];

    return $form;
  }

  /**
   * Retrieves the course subject data and formats it for the table.
   *
   * @return array
   *   The table rows.
   */
  protected function getCourseSubjectRows() {
    $rows = [];
    $course_subjects = $this->maui->getCourseSubjects();

    foreach ($course_subjects as $subject) {
      $additionalInfo = (array) $subject->additionalInfo;
      $rows[] = [
        'naturalKey' => $subject->naturalKey,
        'itchCode' => $additionalInfo['itchCode'] ?? '',
        'description' => $subject->description,
      ];
    }

    return $rows;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // No-op.
  }

}
