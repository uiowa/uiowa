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

    $featured_image_display_default = $config->get('featured_image_display_default');

    $form['global']['featured_image_display_default'] = [
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

    $tag_display = $config->get('tag_display');

    $form['global']['tag_display'] = [
      '#type' => 'select',
      '#title' => $this->t('Display tags in pages'),
      '#description' => $this->t('Set the default way to display a page\'s tags in the page itself.'),
      '#options' => [
        'do_not_display' => $this
          ->t('Do not display tags'),
        'tag_buttons' => $this
          ->t('Display tag buttons'),
      ],
      '#default_value' => $tag_display ?: 'do_not_display',
    ];

    $related_display = $config->get('related_display');

    $form['global']['related_display'] = [
      '#type' => 'select',
      '#title' => $this->t('Display related content in pages'),
      '#description' => $this->t('Set the default way to display a page\'s related content.'),
      '#options' => [
        'do_not_display' => $this
          ->t('Do not display related content'),
        'headings_lists' => $this
          ->t('Display related content as headings and bulleted lists'),
      ],
      '#default_value' => $related_display ?: 'do_not_display',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $featured_image_display_default = $form_state->getValue('featured_image_display_default');
    $tag_display = $form_state->getValue('tag_display');
    $related_display = $form_state->getValue('related_display');
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
    parent::submitForm($form, $form_state);

    drupal_flush_all_caches();
  }

}
