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
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'sitenow_people.settings';
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
    $config = $this->config(static::SETTINGS);
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
      if (isset($display['display_options']['enabled']) && (int) $display['display_options']['enabled'] === 1) {
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
    if ($view->get('status') === TRUE) {
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

    $form['global']['sitenow_people_research_areas'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Override for Research Areas label'),
      '#description' => $this->t('For users visiting the site, Research Areas labels will be overridden with this value. Defaults to <em>Research areas</em>.'),
      '#default_value' => sitenow_people_get_research_title(),
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
      '#default_value' => $config->get('filter_display.combine'),
      '#size' => 60,
    ];
    $form['global']['sitenow_people_filter']['filter_type'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Person Type'),
      '#description' => $this->t('Allow filtering by person type'),
      '#default_value' => $config->get('filter_display.type'),
      '#size' => 60,
    ];

    $form['global']['sitenow_people_filter']['filter_research'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Research Areas'),
      '#description' => $this->t('Allow filtering by Research Areas'),
      '#default_value' => $config->get('filter_display.research'),
      '#size' => 60,
    ];

    $form['global']['sitenow_people_sort'] = [
      '#type' => 'select',
      '#title' => $this->t('Sort'),
      '#options' => $sort_options,
      '#default_value' => $default_sort,
      '#description' => $this->t('Choose the sorting preference for the people listing.'),
    ];

    $form['global']['tags_and_related'] = [
      '#type' => 'fieldset',
      '#title' => 'Tags and related content',
      '#collapsible' => FALSE,
    ];
    $tag_display = $config->get('tag_display');

    $form['global']['tags_and_related']['tag_display'] = [
      '#type' => 'select',
      '#title' => $this->t('Display tags'),
      '#description' => $this->t("Set the default way to display a person's tags in their page."),
      '#options' => [
        'do_not_display' => $this
          ->t('Do not display tags'),
        'tag_buttons' => $this
          ->t('Display tag buttons'),
      ],
      '#default_value' => $tag_display ?: 'do_not_display',
    ];

    $related_display = $config->get('related_display');

    $form['global']['tags_and_related']['related_display'] = [
      '#type' => 'select',
      '#title' => $this->t('Display related content'),
      '#description' => $this->t("Set the default way to display a person's related content."),
      '#options' => [
        'do_not_display' => $this
          ->t('Do not display related content'),
        'headings_lists' => $this
          ->t('Display related content titles grouped by tag'),
      ],
      '#default_value' => $related_display ?: 'do_not_display',
    ];

    $form['global']['tags_and_related']['related_display_headings_lists_help'] = [
      '#type' => 'item',
      '#title' => 'How related content is displayed:',
      '#description' => $this->t("Related content will display above the page's footer as sections of headings (tags) above bulleted lists of a maximum of 30 tagged items. Tagged items are sorted by most recently edited."),
      '#states' => [
        'visible' => [
          ':input[name="related_display"]' => ['value' => 'headings_lists'],
        ],
      ],
    ];

    // show_teaser_link_indicator.
    $is_v2 = $this->config('config_split.config_split.sitenow_v2')->get('status');
    // Visual indicators aren't available on SiteNow v2.
    if (!$is_v2) {
      $form['global']['teaser'] = [
        '#type' => 'fieldset',
        '#title' => 'Teaser display',
        '#collapsible' => FALSE,
      ];
      $show_teaser_link_indicator = $config->get('show_teaser_link_indicator');
      $form['global']['teaser']['show_teaser_link_indicator'] = [
        '#type' => 'checkbox',
        '#title' => $this->t("Display arrows linking to a person's page from lists/teasers."),
        '#default_value' => $show_teaser_link_indicator ?: FALSE,
      ];
    }
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
    $status = (int) $form_state->getValue('sitenow_people_status');
    $title = $form_state->getValue('sitenow_people_title');
    $path = $form_state->getValue('sitenow_people_path');
    $research_title = $form_state->getValue('sitenow_people_research_areas');
    $header_content = $form_state->getValue('sitenow_people_header_content');
    $sort = $form_state->getValue('sitenow_people_sort');
    $tag_display = $form_state->getValue('tag_display');
    $related_display = $form_state->getValue('related_display');
    $show_teaser_link_indicator = $form_state->getValue('show_teaser_link_indicator');
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
    if ($status === 1) {
      $view->set('status', TRUE);
      $enabled_display =& $view->getDisplay($sort);
      $enabled_display["display_options"]["enabled"] = TRUE;
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
    // Save filter display settings.
    $filters = [];
    $filters['combine'] = $form_state->getValue('filter_search');
    $filters['type'] = $form_state->getValue('filter_type');
    $filters['research'] = $form_state->getValue('filter_research');
    $this->configFactory->getEditable(static::SETTINGS)
      ->set('filter_display', $filters)
      ->save();

    $this->configFactory->getEditable(static::SETTINGS)
      // Save the tag display default.
      ->set('tag_display', $tag_display)
      ->save();

    $this->configFactory->getEditable(static::SETTINGS)
      // Save the tag display default.
      ->set('related_display', $related_display)
      ->save();
    parent::submitForm($form, $form_state);

    $this->configFactory->getEditable(static::SETTINGS)
      // Save the research areas label default.
      ->set('research_title', $research_title)
      ->save();

    $this->configFactory->getEditable(static::SETTINGS)
      // Save the tag display default.
      ->set('show_teaser_link_indicator', $show_teaser_link_indicator)
      ->save();

    // Clear cache.
    drupal_flush_all_caches();
  }

}
