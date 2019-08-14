<?php

namespace Drupal\sitenow_people\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Entity\View;

/**
 * Configure SiteNow People settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sitenow_people_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['sitenow_people.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $view = View::load('people');

    // Setup sort options.
    $displays = $view->get('display');
    $sort_options = [];
    $default_sort = 'page_people_slf';
    foreach ($displays as $display) {
      $sort_options[$display['id']] = $display['display_title'];
      if (isset($display["display_options"]["enabled"]) && $display["display_options"]["enabled"] == 1) {
        $default_sort = $display['id'];
      }
    }
    unset($sort_options['default']);
    $default =& $view->getDisplay('default');
    $enabled_display =& $view->getDisplay($default_sort);

    if ($view->get('status') == TRUE) {
      $status = 1;
    }
    else {
      $status = 0;
    }

    $form['markup'] = [
      '#type' => 'markup',
      '#markup' => $this->t('<p>These settings allows you to customize the display of people on the site.</p>'),
    ];

    $form['global'] = [
      '#type' => 'fieldset',
      '#title' => 'Settings',
      '#collapsible' => FALSE,
    ];
    $form['global']['sitenow_people_status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable people listing'),
      '#default_value' => $status,
      '#description' => $this->t('If checked, a people listing will display at the configurable path below.'),
      '#size' => 60,
    ];
    $form['global']['sitenow_people_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('People title'),
      '#description' => $this->t('The title for the people listing. Defaults to <em>People</em>.'),
      '#default_value' => $default['display_options']['title'],
      '#required' => TRUE,
    ];
    $form['global']['sitenow_people_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('People path'),
      '#description' => $this->t('The base path for the people listing. Defaults to <em>people</em>.'),
      '#default_value' => $enabled_display['display_options']['path'],
      '#required' => TRUE,
    ];
    $form['global']['sitenow_people_header_content'] = [
      '#type' => 'text_format',
      '#format' => 'filtered_html',
      '#title' => $this->t('Header Content'),
      '#description' => $this->t('Enter any content that is displayed above the people listing.'),
      '#default_value' => $default["display_options"]["header"]["area"]["content"]["value"],
    ];
    $form['global']['sitenow_people_sort'] = [
      '#type' => 'select',
      '#title' => $this->t('Sort'),
      '#options' => $sort_options,
      '#default_value' => $default_sort,
      '#description' => $this->t('Choose the sorting preference for the people listing.'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Check if path already exists.
    $path = $form_state->getValue('sitenow_people_path');
    // Clean up path first.
    $path = \Drupal::service('pathauto.alias_cleaner')->cleanString($path);
    $path_exists = \Drupal::service('path.alias_storage')->aliasExists('/' . $path, 'en');
    if ($path_exists) {
      $form_state->setErrorByName('path', $this->t('This path is already in-use.'));
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get values.
    $status = $form_state->getValue('sitenow_people_status');
    $title = $form_state->getValue('sitenow_people_title');
    $path = $form_state->getValue('sitenow_people_path');
    $header_content = $form_state->getValue('sitenow_people_header_content');
    $sort = $form_state->getValue('sitenow_people_sort');
    // Clean path.
    $path = \Drupal::service('pathauto.alias_cleaner')->cleanString($path);

    // Load people listing view.
    $view = View::load('people');

    // // Setup sort options.
    $displays = $view->get('display');
    unset($displays['default']);
    foreach ($displays as $display) {
      $display[$display['id']] =& $view->getDisplay($display['id']);
      // Set validated and clean path.
      $display[$display['id']]['display_options']['path'] = $path;
      $display[$display['id']]["display_options"]["enabled"] = FALSE;
    }
    $default =& $view->getDisplay('default');
    // Set title.
    $default['display_options']["title"] = $title;
    // Set header area content.
    $default['display_options']['header']['area']['content']['value'] = $header_content['value'];

    // Enable/Disable view.
    if ($status == 1) {
      $view->set('status', TRUE);
      $enabled_display =& $view->getDisplay($sort);
      $enabled_display["display_options"]["enabled"] = TRUE;
    }
    else {
      $view->set('status', FALSE);
    }

    $view->save();

    // Update person path pattern.
    $config_factory = \Drupal::configFactory();
    $config_factory->getEditable('pathauto.pattern.person')->set('pattern', $path . '/[node:title]')->save();

    // Load and update person node path aliases.
    $entities = [];
    $result = \Drupal::entityQuery('node')->condition('type', 'person')->execute();
    $entity_storage = \Drupal::entityTypeManager()->getStorage('node');
    $entities = array_merge($entities, $entity_storage->loadMultiple($result));
    foreach ($entities as $entity) {
      \Drupal::service('pathauto.generator')->updateEntityAlias($entity, 'update');
    }

    parent::submitForm($form, $form_state);

    // Clear cache.
    drupal_flush_all_caches();
  }

}
