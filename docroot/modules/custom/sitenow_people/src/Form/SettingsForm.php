<?php

namespace Drupal\sitenow_people\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\path_alias\AliasRepositoryInterface;
use Drupal\pathauto\AliasCleanerInterface;
use Drupal\pathauto\PathautoGenerator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure SiteNow People settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The alias cleaner.
   *
   * @var \Drupal\pathauto\AliasCleanerInterface
   */
  protected $aliasCleaner;

  /**
   * The alias checker.
   *
   * @var \Drupal\path_alias\AliasRepositoryInterface
   */
  protected $aliasRepository;

  /**
   * The EntityTypeManager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The PathautoGenerator service.
   *
   * @var \Drupal\pathauto\PathautoGenerator
   */
  protected $pathAutoGenerator;

  /**
   * The Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\pathauto\AliasCleanerInterface $pathauto_alias_cleaner
   *   The alias cleaner.
   * @param \Drupal\path_alias\AliasRepositoryInterface $aliasRepository
   *   The alias checker.
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The EntityTypeManager service.
   * @param \Drupal\pathauto\PathautoGenerator $pathAutoGenerator
   *   The PathautoGenerator service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, AliasCleanerInterface $pathauto_alias_cleaner, AliasRepositoryInterface $aliasRepository, EntityTypeManager $entityTypeManager, PathautoGenerator $pathAutoGenerator) {
    parent::__construct($config_factory);
    $this->aliasCleaner = $pathauto_alias_cleaner;
    $this->aliasRepository = $aliasRepository;
    $this->entityTypeManager = $entityTypeManager;
    $this->pathAutoGenerator = $pathAutoGenerator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('pathauto.alias_cleaner'),
      $container->get('path_alias.repository'),
      $container->get('entity_type.manager'),
      $container->get('pathauto.generator')
    );
  }

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
    return [
      'sitenow_people.settings',
      'pathauto.pattern.person',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $view = $this->entityTypeManager->getStorage('view')->load('people');

    // Setup sort options by getting all displays.
    $displays = $view->get('display');
    $sort_options = [];
    // Set Sticky/Last/First as default.
    $default_sort = 'page_people_slf';
    foreach ($displays as $display) {
      $sort_options[$display['id']] = $display['display_title'];
      // Override the default sort value. Assumes only one display is enabled...
      if (isset($display['display_options']['enabled']) && $display['display_options']['enabled'] == 1) {
        $default_sort = $display['id'];
      }
    }
    // Remove Master display from options.
    unset($sort_options['default']);

    // Get the default view settings.
    $default =& $view->getDisplay('default');
    // Get the enabled display.
    $enabled_display =& $view->getDisplay($default_sort);

    // Get view_people status.
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
      '#default_value' => $default['display_options']['header']['area']['content']['value'],
    ];

    // Future filter options go here.
    $form['global']['sitenow_people_filter'] = [
      '#type' => 'fieldset',
      '#title' => 'Filter',
      '#description' => $this->t('Allow visitors to filter people by one or more of the following options.'),
      '#collapsible' => FALSE,
    ];
    $form['global']['sitenow_people_filter']['filter_search'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Search'),
      '#description' => $this->t('Allow filtering by name'),
      '#default_value' => isset($default["display_options"]["filters"]["combine"]),
      '#size' => 60,
    ];
    $form['global']['sitenow_people_filter']['filter_type'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Person Type'),
      '#description' => $this->t('Allow filtering by person type'),
      '#default_value' => isset($default["display_options"]["filters"]["field_person_types_target_id"]),
      '#size' => 60,
    ];
    $form['global']['sitenow_people_filter']['filter_research'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Research Area'),
      '#description' => $this->t('Allow filtering by research area'),
      '#default_value' => isset($default["display_options"]["filters"]["field_person_research_areas_target_id"]),
      '#size' => 60,
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
    $path = $this->aliasCleaner->cleanString($path);
    $path_exists = $this->aliasRepository->lookupByAlias('/' . $path, 'en');

    if ($path_exists) {
      $form_state->setErrorByName('path', $this->t('This path is already in use.'));
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get values.
    $filters = [];
    $status = $form_state->getValue('sitenow_people_status');
    $title = $form_state->getValue('sitenow_people_title');
    $path = $form_state->getValue('sitenow_people_path');
    $header_content = $form_state->getValue('sitenow_people_header_content');
    $filters['combine'] = $form_state->getValue('filter_search');
    $filters['field_person_types_target_id'] = $form_state->getValue('filter_type');
    $filters['field_person_research_areas_target_id'] = $form_state->getValue('filter_research');
    $sort = $form_state->getValue('sitenow_people_sort');

    // Clean path.
    $path = $this->aliasCleaner->cleanString($path);

    // Load people listing view.
    $view = $this->entityTypeManager->getStorage('view')->load('people');

    // For all displays but Master, disable and set path.
    $displays = $view->get('display');
    unset($displays['default']);
    foreach ($displays as $display) {
      $displays[$display['id']] =& $view->getDisplay($display['id']);
      // Set validated and clean path.
      $displays[$display['id']]['display_options']['path'] = $path;
      $displays[$display['id']]['display_options']['enabled'] = FALSE;
    }

    // Set default display to set global settings.
    $default =& $view->getDisplay('default');
    // Set title.
    $default['display_options']['title'] = $title;
    // Set header area content.
    $default['display_options']['header']['area']['content']['value'] = $header_content['value'];

    // Enable/Disable view_people and set selected "sort" as enabled display.
    if ($status == 1) {
      $view->set('status', TRUE);
      $enabled_display =& $view->getDisplay($sort);
      $enabled_display["display_options"]["enabled"] = TRUE;

      // Loop through and toggle filters based on form selections.
      // @todo Store as configuration and just toggle the exposed status.
      // Currently causes no results because the filters fire blank values.
      foreach ($filters as $key => $filter) {
        // Unset all so that they stay in order.
        unset($default["display_options"]["filters"][$key]);
        if ($filter == 1) {
          if ($key == 'combine') {
            $default["display_options"]["filters"][$key] = [
              'id' => 'combine',
              'table' => 'views',
              'field' => 'combine',
              'relationship' => 'none',
              'group_type' => 'group',
              'admin_label' => '',
              'operator' => 'contains',
              'value' => '',
              'group' => 1,
              'exposed' => 1,
              'expose' => [
                'operator_id' => 'combine_op',
                'label' => 'Search',
                'description' => '',
                'use_operator' => FALSE,
                'operator' => 'combine_op',
                'operator_limit_selection' => FALSE,
                'operator_list' => [],
                'identifier' => 'search',
                'required' => FALSE,
                'remember' => FALSE,
                'multiple' => FALSE,
                'remember_roles' => [
                  'authenticated' => 'authenticated',
                  'anonymous' => '0',
                  'viewer' => '0',
                  'editor' => '0',
                  'publisher' => '0',
                  'webmaster' => '0',
                  'administrator' => '0',
                ],
                'placeholder' => 'Search by name',
              ],
              'is_grouped' => FALSE,
              'group_info' => [
                'label' => "",
                'description' => '',
                'identifier' => '',
                'optional' => TRUE,
                'widget' => 'select',
                'multiple' => FALSE,
                'remember' => FALSE,
                'default_group' => 'All',
                'default_group_multiple' => [],
                'group_items' => [],
              ],
              'fields' => [
                'title' => 'title',
              ],
              'plugin_id' => 'combine',
            ];
          }
          if ($key == 'field_person_types_target_id') {
            $default["display_options"]["filters"][$key] = [
              'id' => 'field_person_types_target_id',
              'table' => 'node__field_person_types',
              'field' => 'field_person_types_target_id',
              'relationship' => 'none',
              'group_type' => 'group',
              'admin_label' => '',
              'operator' => '=',
              'value' => '',
              'group' => 1,
              'exposed' => 1,
              'expose' => [
                'operator_id' => 'field_person_types_target_id_op',
                'label' => 'Person Type',
                'description' => '',
                'use_operator' => FALSE,
                'operator' => 'field_person_types_target_id_op',
                'operator_limit_selection' => FALSE,
                'operator_list' => [],
                'identifier' => 'type',
                'required' => FALSE,
                'remember' => FALSE,
                'multiple' => FALSE,
                'remember_roles' => [
                  'authenticated' => 'authenticated',
                  'anonymous' => '0',
                  'viewer' => '0',
                  'editor' => '0',
                  'publisher' => '0',
                  'webmaster' => '0',
                  'administrator' => '0',
                ],
                'placeholder' => '',
              ],
              'is_grouped' => FALSE,
              'group_info' => [
                'label' => "",
                'description' => '',
                'identifier' => '',
                'optional' => TRUE,
                'widget' => 'select',
                'multiple' => FALSE,
                'remember' => FALSE,
                'default_group' => 'All',
                'default_group_multiple' => [],
                'group_items' => [],
              ],
              'plugin_id' => 'string',
            ];
          }
          if ($key == 'field_person_research_areas_target_id') {
            $default["display_options"]["filters"][$key] = [
              'id' => 'field_person_research_areas_target_id',
              'table' => 'node__field_person_research_areas',
              'field' => 'field_person_research_areas_target_id',
              'relationship' => 'none',
              'group_type' => 'group',
              'admin_label' => '',
              'operator' => 'or',
              'value' => [],
              'group' => 1,
              'exposed' => 1,
              'expose' => [
                'operator_id' => 'field_person_research_areas_target_id_op',
                'label' => 'Research Area',
                'description' => '',
                'use_operator' => FALSE,
                'operator' => 'field_person_research_areas_target_id_op',
                'operator_limit_selection' => FALSE,
                'operator_list' => [],
                'identifier' => 'research',
                'required' => FALSE,
                'remember' => FALSE,
                'multiple' => FALSE,
                'remember_roles' => [
                  'authenticated' => 'authenticated',
                  'anonymous' => '0',
                  'viewer' => '0',
                  'editor' => '0',
                  'publisher' => '0',
                  'webmaster' => '0',
                  'administrator' => '0',
                ],
                'reduce' => FALSE,
              ],
              'is_grouped' => FALSE,
              'group_info' => [
                'label' => "",
                'description' => '',
                'identifier' => '',
                'optional' => TRUE,
                'widget' => 'select',
                'multiple' => FALSE,
                'remember' => FALSE,
                'default_group' => 'All',
                'default_group_multiple' => [],
                'group_items' => [],
              ],
              "reduce_duplicates" => FALSE,
              "type" => 'select',
              "limit" => TRUE,
              "vid" => 'research_areas',
              "hierarchy" => FALSE,
              'error_message' => TRUE,
              'parent' => 0,
              'level_labels' => FALSE,
              'force_deepest' => FALSE,
              'save_lineage' => FALSE,
              'hierarchy_depth' => 0,
              'required_depth' => 0,
              'none_label' => '- Please select -',
              'plugin_id' => 'taxonomy_index_tid',
            ];
          }
        }
      }
    }
    else {
      $view->set('status', FALSE);
    }

    $view->save();

    $old_pattern = $this->config('pathauto.pattern.person')->get('pattern');

    $new_pattern = $path . '/[node:title]';

    // Only run this potentially expensive process if this setting is changing.
    if ($new_pattern != $old_pattern) {
      // Update person path pattern.
      $this->config('pathauto.pattern.person')
        ->set('pattern', $new_pattern)
        ->save();

      // Load and update person node path aliases.
      $entities = $this->entityTypeManager->getStorage('node')
        ->loadByProperties(['type' => 'person']);

      foreach ($entities as $entity) {
        $this->pathAutoGenerator->updateEntityAlias($entity, 'update');
      }
    }

    parent::submitForm($form, $form_state);

    // Clear cache.
    drupal_flush_all_caches();
  }

}
