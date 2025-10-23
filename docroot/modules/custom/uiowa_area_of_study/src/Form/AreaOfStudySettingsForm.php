<?php

namespace Drupal\uiowa_area_of_study\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure area of study settings for this site.
 */
class AreaOfStudySettingsForm extends ConfigFormBase {

  /**
   * Settings config name.
   */
  const SETTINGS = 'uiowa_area_of_study.settings';

  /**
   * The config split manager.
   *
   * @var \Drupal\config_split\ConfigSplitManager
   */
  protected $configSplitManager;

  /**
   * The EntityTypeManager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new AreaOfStudySettingsForm.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($config_factory);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'uiowa_area_of_study_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'uiowa_area_of_study.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);
    $form = parent::buildForm($form, $form_state);

    $form['markup'] = [
      '#type' => 'markup',
      '#markup' => $this->t('<p>These settings let you configure the SiteNow Areas of Study feature.</p>'),
    ];

    $form['global'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Site-wide settings'),
      '#description' => $this->t('These settings affect all areas of study lists and single instances.'),
    ];

    $form['global']['areas_of_study_degree_types'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Override for Degree Types label'),
      '#description' => $this->t('The Degree Types label will be overridden with this value.'),
      '#default_value' => uiowa_area_of_study_get_field_label('field_area_of_study_degree_types', 'degree_types'),
      '#required' => TRUE,
    ];

    $form['global']['areas_of_study_locations'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Override for Locations label'),
      '#description' => $this->t('The Locations label will be overridden with this value.'),
      '#default_value' => uiowa_area_of_study_get_field_label('field_area_of_study_locations', 'locations'),
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $degree_type = $form_state->getValue('areas_of_study_degree_types');
    $location = $form_state->getValue('areas_of_study_locations');

    $this->configFactory->getEditable(static::SETTINGS)
      ->set('degree_types', $degree_type)
      ->set('locations', $location)
      ->save();

    parent::submitForm($form, $form_state);

    // Clear cache.
    drupal_flush_all_caches();
  }

}
