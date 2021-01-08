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
class DatesBySessionForm extends FormBase {
  /**
   * The MAUI API service.
   *
   * @var MauiApi
   */
  protected $maui;

  /**
   * DatesBySessionForm constructor.
   *
   * @param MauiApi $maui
   */
  public function __construct(MauiApi $maui) {
    $this->maui = $maui;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('uiowa_maui.api')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'uiowa_maui_dates_by_session';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $current = $form_state->getValue('session') ?? $this->maui->getCurrentSession()->id;
    $category = $form_state->getValue('category') ?? NULL;

    // Get a list of sessions for the select list options.
    $sessions = $this->maui->getSessionsBounded(10, 10);
    $options = array();

    foreach ($sessions as $session) {
      $options[$session->id] = Html::escape($session->shortDescription);
    }

    $form['session'] = [
      '#type' => 'select',
      '#title' => t('Session'),
      '#description' => t('Select a session to show dates for.'),
      '#default_value' => $current,
      '#options' => $options,
      '#ajax' => [
        'callback' => '::ajaxResponse',
        'wrapper' => 'maui-dates',
      ],
    ];

    $form['category'] = [
      '#type' => 'select',
      '#title' => t('Category'),
      '#description' => t('Select a category to filter dates on.'),
      '#default_value' => $category,
      '#empty_value' => NULL,
      '#empty_option' => t('- All -'),
      '#options' => $this->maui->getDateCategories(),
      '#ajax' => [
        'callback' => '::ajaxResponse',
        'wrapper' => 'maui-dates',
      ],
    ];

    $dates = $this->maui->getSessionDates($current, $category);

    // This ID needs to be different than the form ID.
    $form['dates'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'maui-dates',
      ],
      '#markup' => t('Dates for @session and category: @category.', [
        '@session' => $current,
        '@category' => $category ?? 'All',
      ]),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // @todo: Validate session and category form values.
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // No-op.
  }

  public function ajaxResponse(array &$form, FormStateInterface $form_state) {
    return $form['dates'];
  }

}
