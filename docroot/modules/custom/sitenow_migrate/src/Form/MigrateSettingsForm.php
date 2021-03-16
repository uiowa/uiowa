<?php

namespace Drupal\sitenow_migrate\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure UIowa Events settings for this site.
 */
class MigrateSettingsForm extends ConfigFormBase {
  /**
   * The config.storage service.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $configStorage;

  /**
   * Form constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config.factory service.
   * @param \Drupal\Core\Config\StorageInterface $configStorage
   *   The config.storage service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, StorageInterface $configStorage) {
    parent::__construct($config_factory);
    $this->configStorage = $configStorage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
    $container->get('config.factory'),
    $container->get('config.storage')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sitenow_migrate_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'migrate_plus.migration_group.sitenow_migrate',
      'migrate_plus.migration.d7_file',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $migrate_group_sitenow_migrate_config = $this->config('migrate_plus.migration_group.sitenow_migrate');
    $migrate_plus_d7_file_config = $this->config('migrate_plus.migration.d7_file');

    $form['markup'] = [
      '#type' => 'markup',
      '#markup' => $this->t('<p>These settings let you configure SiteNow Migrate for use on this site.</p>'),
    ];

    $form['database'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Database Settings'),
      '#description' => $this->t('Configuration needed to connect and migrate database content from a remote site. <strong>Note</strong> only MySQL driver support at this time.'),
    ];

    $form['database']['database'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Database Name'),
      '#description' => $this->t('The database name to pull from. e.g. standard_itaccessibility.'),
      '#default_value' => $migrate_group_sitenow_migrate_config->get('shared_configuration.source.database.database'),
      '#required' => TRUE,
    ];

    $form['database']['username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Database User'),
      '#description' => $this->t('The database user with access to the database. Use root for DevDesktop.'),
      '#default_value' => $migrate_group_sitenow_migrate_config->get('shared_configuration.source.database.username'),
      '#required' => TRUE,
    ];

    $form['database']['password'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Database User Password'),
      '#description' => $this->t('The database user password, if applicable. Leave empty for DevDesktop.'),
      '#default_value' => $migrate_group_sitenow_migrate_config->get('shared_configuration.source.database.password'),
    ];

    $form['database']['host'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Database Host'),
      '#description' => $this->t('The database host. Use 10.0.2.2 for DevDesktop from DrupalVM.'),
      '#default_value' => $migrate_group_sitenow_migrate_config->get('shared_configuration.source.database.host'),
      '#required' => TRUE,
    ];

    $form['database']['port'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Database Host Port'),
      '#description' => $this->t('The database host port. Use 33067 for DevDesktop.'),
      '#default_value' => $migrate_group_sitenow_migrate_config->get('shared_configuration.source.database.port'),
      '#required' => TRUE,
    ];

    // Only allow migration settings to be saved if config entities exist.
    if (!$migrate_plus_d7_file_config->isNew()) {
      $form['files'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('File Settings'),
        '#description' => $this->t('Production files path. e.g. https://itaccessibility.uiowa.edu/sites/itaccessibility.uiowa.edu/files/'),
      ];

      $form['files']['sitenow_migrate_file_path'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Files Path'),
        '#description' => $this->t('The files path to pull from'),
        '#default_value' => $migrate_plus_d7_file_config->get('source.constants.SOURCE_BASE_PATH'),
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $migrate_group_sitenow_migrate_config = $this->config('migrate_plus.migration_group.sitenow_migrate');

    // Its entirely possible to use sitenow_migrate without importing the split
    // config. If that is the case, import the initial migration group config.
    if ($migrate_group_sitenow_migrate_config->isNew()) {
      $config_path = DRUPAL_ROOT . '/../config/features/sitenow_migrate';
      $source = new FileStorage($config_path);
      $this->configStorage->write('migrate_plus.migration_group.sitenow_migrate', $source->read('migrate_plus.migration_group.sitenow_migrate'));
    }

    $shared_db_config = [
      'database',
      'username',
      'password',
      'host',
      'port',
    ];

    foreach ($shared_db_config as $config) {
      $this->config('migrate_plus.migration_group.sitenow_migrate')
        ->set("shared_configuration.source.database.{$config}", $form_state->getValue($config))
        ->save();
    }

    if ($form_state->getValue('sitenow_migrate_file_path')) {
      $this->config('migrate_plus.migration.d7_file')
        ->set('source.constants.SOURCE_BASE_PATH', $form_state->getValue('sitenow_migrate_file_path'))
        ->save();

      // Set file directory to avoid needing dynamic setting during migration.
      $this->config('migrate_plus.migration.d7_file')
        ->set('source.constants.DRUPAL_FILE_DIRECTORY', 'public://' . date('Y-m'))
        ->save();
    }

    parent::submitForm($form, $form_state);
  }

}
