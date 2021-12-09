<?php

namespace Drupal\uiowa_hours\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\uiowa_hours\HoursApi;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Resource Hours settings for this site.
 */
class SettingsForm extends ConfigFormBase {
  /**
   * The Hours API service.
   *
   * @var \Drupal\uiowa_hours\HoursApi
   */
  protected $hours;

  /**
   * HoursFilterForm constructor.
   *
   * @param \Drupal\uiowa_hours\HoursApi $hours
   *   The Hours API service.
   */
  public function __construct(HoursApi $hours) {
    $this->hours = $hours;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('uiowa_hours.api')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'uiowa_hours_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['uiowa_hours.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $groups = $this->hours->getGroups();

    $form['group'] = [
      '#type' => 'select',
      '#title' => $this->t('Group'),
      '#description' => $this->t('Select the resource group to use for this site. This will determine what resources are available in the hours block.'),
      '#default_value' => $this->config('uiowa_hours.settings')->get('group'),
      '#options' => array_combine($groups, $groups),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('uiowa_hours.settings')
      ->set('group', $form_state->getValue('group'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
