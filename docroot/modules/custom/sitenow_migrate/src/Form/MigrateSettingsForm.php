<?php

namespace Drupal\sitenow_migrate\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure UIowa Events settings for this site.
 */
class MigrateSettingsForm extends ConfigFormBase {

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
    return ['migrate_plus.migration_group.sitenow_migrate', 'migrate_plus.migration.d7_file'];
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
      '#description' => $this->t('Configuration needed to connect and migrate database content from a remote site.'),
    ];

    $form['database']['sitenow_migrate_database_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Database Name'),
      '#description' => $this->t('The local database name to pull from. e.g. standard_itaccessibility'),
      '#default_value' => $migrate_group_sitenow_migrate_config->get('shared_configuration.source.database.database'),
      '#required' => TRUE,
    ];
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
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    \Drupal::configFactory()->getEditable('migrate_plus.migration_group.sitenow_migrate')
      ->set('shared_configuration.source.database.database', $form_state->getValue('sitenow_migrate_database_name'))
      ->save();
    \Drupal::configFactory()->getEditable('migrate_plus.migration.d7_file')
      ->set('source.constants.SOURCE_BASE_PATH', $form_state->getValue('sitenow_migrate_file_path'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
