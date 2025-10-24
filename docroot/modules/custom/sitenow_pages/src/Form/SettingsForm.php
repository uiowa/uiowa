<?php

namespace Drupal\sitenow_pages\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\path_alias\AliasRepositoryInterface;
use Drupal\pathauto\AliasCleanerInterface;
use Drupal\pathauto\PathautoGenerator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure SiteNow Pages settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'sitenow_pages.settings';

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
    return 'sitenow_pages_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::SETTINGS,
      'pathauto.pattern.article',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);
    $form = parent::buildForm($form, $form_state);

    $form['node'] = [
      '#type' => 'fieldset',
      '#title' => 'Page node settings',
      '#collapsible' => FALSE,
    ];

    $form['node']['help'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Customize settings for individual article nodes.'),
    ];

    $form['node']['featured_image_display_default'] = [
      '#type' => 'select',
      '#title' => $this->t('Display featured image'),
      '#description' => $this->t('Set the default behavior for how to display a featured image.'),
      '#options' => [
        'do_not_display' => $this
          ->t('Do not display'),
        'small' => $this
          ->t('Small'),
        'medium' => $this
          ->t('Medium'),
        'large' => $this
          ->t('Large'),
      ],
      '#default_value' => $config->get('featured_image_display_default') ?: 'large',
    ];

    $form['node']['tags_and_related'] = [
      '#type' => 'fieldset',
      '#title' => 'Tags and related content',
      '#collapsible' => FALSE,
    ];

    $form['node']['tags_and_related']['tag_display'] = [
      '#type' => 'select',
      '#title' => $this->t('Display tags'),
      '#description' => $this->t("Set the default way to display a page's tags in the page itself."),
      '#options' => [
        'do_not_display' => $this
          ->t('Do not display tags'),
        'tag_buttons' => $this
          ->t('Display tag buttons'),
      ],
      '#default_value' => $config->get('tag_display') ?: 'do_not_display',
    ];

    $form['node']['tags_and_related']['related_display'] = [
      '#type' => 'select',
      '#title' => $this->t('Display related content'),
      '#description' => $this->t('Which related content should be displayed?.'),
      '#options' => [
        'do_not_display' => $this
          ->t('None'),
        'headings_lists' => $this
          ->t('Content with the same tags, grouped by tag'),
      ],
      '#default_value' => $config->get('related_display') ?: 'do_not_display',
    ];

    $form['node']['tags_and_related']['related_display_headings_lists_help'] = [
      '#type' => 'item',
      '#title' => 'How related content is displayed:',
      '#description' => $this->t("Related content will display above the page's footer as sections of headings (tags) above bulleted lists of a maximum of 30 tagged items. Tagged items are sorted by most recently edited."),
      '#states' => [
        'visible' => [
          ':input[name="related_display"]' => ['value' => 'headings_lists'],
        ],
      ],
    ];

    // Display a checkbox that allows the user to choose whether to customize
    // the title shown above related content. If checked, the related content
    // title field will be shown.
    $form['node']['tags_and_related']['custom_related_title'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Customize title above related content'),
      '#description' => $this->t('Check this box to set a custom title that appears above related content. If unchecked, the default title <em>Related content</em> will be used.'),
      '#default_value' => (bool) $config->get('related_title'),
    ];

    $form['node']['tags_and_related']['related_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title to display above related content'),
      '#description' => $this->t('Set the title that appears above related content. Defaults to <em>Related content</em>.'),
      '#default_value' => $config->get('related_title') ?: 'Related content',
      '#states' => [
        'visible' => [
          ':input[name="custom_related_title"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Visual indicators aren't available on SiteNow v2.
    $is_v2 = $this->config('config_split.config_split.sitenow_v2')->get('status');
    if (!$is_v2) {
      $form['teaser'] = [
        '#type' => 'fieldset',
        '#title' => 'Teaser settings',
        '#collapsible' => FALSE,
      ];
      $show_teaser_link_indicator = $config->get('show_teaser_link_indicator');
      $form['teaser']['show_teaser_link_indicator'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Display arrows linking to pages from lists/teasers.'),
        '#default_value' => $show_teaser_link_indicator ?: FALSE,
      ];

      $form['block_settings'] = [
        '#type' => 'fieldset',
        '#title' => 'Layout Builder settings',
        '#collapsible' => FALSE,
      ];

      $form['block_settings']['card_link_indicator_display'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Display card arrow button'),
        '#description' => $this->t('Set the default behavior the card arrow button.'),
        '#default_value' => $config->get('card_link_indicator_display') ?? TRUE,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config_settings = $this->configFactory->getEditable(static::SETTINGS);

    // List of config updates to make.
    $config_updates = [
      'featured_image_display_default',
      'tag_display',
      'related_display',
      'related_title',
      'show_teaser_link_indicator',
      'card_link_indicator_display',
    ];

    // Update each config item in the list from the form state.
    foreach ($config_updates as $config_name) {
      $config_settings->set($config_name,
        $form_state->getValue($config_name))
        ->save();
    }

    parent::submitForm($form, $form_state);

    drupal_flush_all_caches();
  }

}
