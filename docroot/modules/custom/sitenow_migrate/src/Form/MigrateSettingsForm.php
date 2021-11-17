<?php

namespace Drupal\sitenow_migrate\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\CachedDiscoveryClearerInterface;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
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
   * Migration plugin manager service.
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManager
   */
  protected $migrationPluginManager;

  /**
   * The plugin.cache_clearer service.
   *
   * @var \Drupal\Core\Plugin\CachedDiscoveryClearerInterface
   */
  protected $pluginCacheClearer;

  /**
   * Form constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config.factory service.
   * @param \Drupal\Core\Config\StorageInterface $configStorage
   *   The config.storage service.
   * @param \Drupal\migrate\Plugin\MigrationPluginManagerInterface $migrationPluginManager
   *   The plugin.manager.migration service.
   * @param \Drupal\Core\Plugin\CachedDiscoveryClearerInterface $pluginCacheClearer
   *   The plugin.cache_clearer service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, StorageInterface $configStorage, MigrationPluginManagerInterface $migrationPluginManager, CachedDiscoveryClearerInterface $pluginCacheClearer) {
    parent::__construct($config_factory);
    $this->configStorage = $configStorage;
    $this->migrationPluginManager = $migrationPluginManager;
    $this->pluginCacheClearer = $pluginCacheClearer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
    $container->get('config.factory'),
    $container->get('config.storage'),
    $container->get('plugin.manager.migration'),
    $container->get('plugin.cache_clearer')
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
    $config = $this->config('migrate_plus.migration_group.sitenow_migrate');

    // This ensures that the sitenow_migrate and default migration group
    // configuration exists. This is necessary because that configuration is
    // ignored and this form needs to save data to the sitenow_migrate config.
    if ($config->isNew()) {
      $this->pluginCacheClearer->clearCachedDefinitions();
      $this->migrationPluginManager->createInstances([]);
    }

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
      '#default_value' => $config->get('shared_configuration.source.database.database'),
      '#required' => TRUE,
    ];

    $form['database']['username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Database User'),
      '#description' => $this->t('The database user with access to the database. Use root for DevDesktop.'),
      '#default_value' => $config->get('shared_configuration.source.database.username'),
      '#required' => TRUE,
    ];

    $form['database']['password'] = [
      '#type' => 'password',
      '#title' => $this->t('Database User Password'),
      '#description' => $this->t('The database user password, if applicable. Leave empty for DevDesktop.'),
      '#default_value' => $config->get('shared_configuration.source.database.password'),
    ];

    $form['database']['host'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Database Host'),
      '#description' => $this->t('The database host. Use 10.0.2.2 for DevDesktop from DrupalVM.'),
      '#default_value' => $config->get('shared_configuration.source.database.host'),
      '#required' => TRUE,
    ];

    $form['database']['port'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Database Host Port'),
      '#description' => $this->t('The database host port. The MySQL default port is 3306 but use 33067 for DevDesktop.'),
      '#default_value' => $config->get('shared_configuration.source.database.port'),
      '#required' => TRUE,
    ];

    $form['constants'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Paths'),
      '#description' => $this->t('Path constants used in migrations.'),
    ];

    $form['constants']['source_base_path'] = [
      '#type' => 'url',
      '#title' => $this->t('Base URL'),
      '#description' => $this->t('The full URL to the site.'),
      '#default_value' => $config->get('shared_configuration.source.constants.source_base_path'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $shared_config = [
      'source' => [
        'key' => 'drupal_7',
        'constants' => [
          'source_base_path' => $form_state->getValue('source_base_path'),
          'drupal_file_directory' => 'public://' . date('Y-m'),
        ],
        'database' => [
          'database' => $form_state->getValue('database'),
          'username' => $form_state->getValue('username'),
          'password' => $form_state->getValue('password'),
          'host' => $form_state->getValue('host'),
          'port' => $form_state->getValue('port'),
          'driver' => 'mysql',
          'prefix' => NULL,
        ],
      ],
    ];

    $this->config('migrate_plus.migration_group.sitenow_migrate')
      ->set('shared_configuration', $shared_config)
      ->save();

    // If the DB connection changes, this is required to reflect that in
    // the Drush migrate:status command. Save developers from running a CR.
    $this->pluginCacheClearer->clearCachedDefinitions();

    parent::submitForm($form, $form_state);
  }

}
