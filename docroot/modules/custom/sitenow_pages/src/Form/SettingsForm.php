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
   */
  protected AliasCleanerInterface $aliasCleaner;

  /**
   * The alias checker.
   */
  protected AliasRepositoryInterface $aliasRepository;

  /**
   * The EntityTypeManager service.
   */
  protected EntityTypeManager $entityTypeManager;

  /**
   * The PathautoGenerator service.
   */
  protected PathautoGenerator $pathAutoGenerator;

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

    $featured_image_display_default = $config->get('featured_image_display_default');

    $form['global']['featured_image'] = [
      '#type' => 'fieldset',
      '#title' => 'Featured image',
      '#collapsible' => FALSE,
    ];

    $form['global']['featured_image']['featured_image_display_default'] = [
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
      '#default_value' => $featured_image_display_default ?: 'large',
    ];

    $form['global']['tags_and_related'] = [
      '#type' => 'fieldset',
      '#title' => 'Tags and related content',
      '#collapsible' => FALSE,
    ];

    $tag_display = $config->get('tag_display');

    $form['global']['tags_and_related']['tag_display'] = [
      '#type' => 'select',
      '#title' => $this->t('Display tags in pages'),
      '#description' => $this->t("Set the default way to display a page's tags in the page itself."),
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
      '#description' => $this->t("Set the default way to display a page's related content."),
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

    // Visual indicators aren't available on SiteNow v2.
    $is_v2 = $this->config('config_split.config_split.sitenow_v2')->get('status');
    if (!$is_v2) {
      $form['global']['teaser'] = [
        '#type' => 'fieldset',
        '#title' => 'Teaser display',
        '#collapsible' => FALSE,
      ];
      $show_teaser_link_indicator = $config->get('show_teaser_link_indicator');
      $form['global']['teaser']['show_teaser_link_indicator'] = [
        '#type' => 'checkbox',
        '#title' => $this->t("Display arrows linking to pages from lists/teasers."),
        '#default_value' => $show_teaser_link_indicator ?: FALSE,
      ];

      $form['global']['block_settings'] = [
        '#type' => 'fieldset',
        '#title' => 'Block Settings',
        '#collapsible' => FALSE,
      ];

      $form['global']['block_settings']['card_link_indicator_display'] = [
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

    $featured_image_display_default = $form_state->getValue('featured_image_display_default');
    $tag_display = $form_state->getValue('tag_display');
    $related_display = $form_state->getValue('related_display');
    $show_teaser_link_indicator = $form_state->getValue('show_teaser_link_indicator');
    $card_link_indicator_display = $form_state->getValue('card_link_indicator_display');

    $this->configFactory->getEditable(static::SETTINGS)
      // Save the featured image display default.
      ->set('featured_image_display_default', $featured_image_display_default)
      ->save();

    $this->configFactory->getEditable(static::SETTINGS)
      // Save the tag display default.
      ->set('tag_display', $tag_display)
      ->save();

    $this->configFactory->getEditable(static::SETTINGS)
      // Save the tag display default.
      ->set('related_display', $related_display)
      ->save();

    $this->configFactory->getEditable(static::SETTINGS)
      // Save the tag display default.
      ->set('show_teaser_link_indicator', $show_teaser_link_indicator)
      ->save();

    $this->configFactory->getEditable(static::SETTINGS)
      // Save the default card button selection.
      ->set('card_link_indicator_display', $card_link_indicator_display)
      ->save();

    parent::submitForm($form, $form_state);

    drupal_flush_all_caches();
  }

}
