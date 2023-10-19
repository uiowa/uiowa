<?php

namespace Drupal\sitenow_articles\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\path_alias\AliasRepositoryInterface;
use Drupal\pathauto\AliasCleanerInterface;
use Drupal\pathauto\PathautoGenerator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure UIowa Articles settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'sitenow_articles.settings';

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
    return 'sitenow_articles_settings';
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
    $view = $this->entityTypeManager->getStorage('view')->load('articles');
    $display =& $view->getDisplay('page_articles');
    $archive =& $view->getDisplay('block_articles_archive');
    $feed =& $view->getDisplay('feed_articles');

    if ($feed['display_options']['displays']['page_articles'] === 'page_articles') {
      $show_feed = 1;
    }
    else {
      $show_feed = 0;
    }
    if ($archive['display_options']['enabled'] === TRUE) {
      $show_archive = 1;
    }
    else {
      $show_archive = 0;
    }
    $default =& $view->getDisplay('default');
    if ($display['display_options']['enabled'] === TRUE) {
      $status = 1;
    }
    else {
      $status = 0;
    }

    $form['markup'] = [
      '#type' => 'markup',
      '#markup' => $this->t('<p>These settings allows you to customize the display of articles on the site.</p>'),
    ];

    $form['article_node'] = [
      '#type' => 'fieldset',
      '#title' => 'Article Settings',
      '#collapsible' => FALSE,
    ];

    $featured_image_display_default = $config->get('featured_image_display_default');

    $form['article_node']['featured_image_display_default'] = [
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

    $form['article_node']['tag_display'] = [
      '#type' => 'select',
      '#title' => $this->t('Display tags'),
      '#description' => $this->t("Set the default way to display an article's tags in the article itself."),
      '#options' => [
        'do_not_display' => $this
          ->t('Do not display tags'),
        'tag_buttons' => $this
          ->t('Display tag buttons'),
      ],
      '#default_value' => $tag_display ?: 'do_not_display',
    ];

    $related_display = $config->get('related_display');

    $form['article_node']['related_display'] = [
      '#type' => 'select',
      '#title' => $this->t('Display related content'),
      '#description' => $this->t("Set the default way to display an article's related content."),
      '#options' => [
        'card_grid' => $this
          ->t('Display manually referenced related content'),
        'headings_lists' => $this
          ->t('Display related content titles grouped by tag'),
      ],
      '#default_value' => $related_display ?: 'card_grid',
    ];

    $form['article_node']['related_display_headings_lists_help'] = [
      '#type' => 'item',
      '#title' => 'How related content is displayed:',
      '#description' => $this->t("Related content will display above the page's footer as sections of headings (tags) above bulleted lists of a maximum of 30 tagged items. Tagged items are sorted by most recently edited."),
      '#states' => [
        'visible' => [
          ':input[name="related_display"]' => ['value' => 'headings_lists'],
        ],
      ],
    ];

    $form['article_node']['preserved_links_message_display'] = [
      '#type' => 'text_format',
      '#format' => 'basic',
      '#allowed_formats' => [
        'basic',
      ],
      '#title' => $this->t('Preserved links message'),
      '#description' => $this->t('Set the message to display when an article may have broken links. If no message is provided, a default message will be used.'),
      '#default_value' => $config->get('preserved_links_message_display') ?? $config->get('preserved_links_message_display_default'),
      '#attributes' => [
        'placeholder' => $config->get('preserved_links_message_display_default'),
      ],
    ];

    $form['article_author'] = [
      '#type' => 'fieldset',
      '#title' => 'Author Settings',
      '#collapsible' => FALSE,
    ];

    $display_articles_by_author = $config->get('display_articles_by_author');

    $form['article_author']['display_articles_by_author'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display listing of article authored by a person on their person page'),
      '#description' => $this->t('If checked, articles authored by a person are listed on their page.'),
      '#default_value' => $display_articles_by_author ?: FALSE,
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
    }
    $form['view_page'] = [
      '#type' => 'fieldset',
      '#title' => 'View Page Settings',
      '#collapsible' => FALSE,
    ];

    $form['view_page']['sitenow_articles_status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable articles listing'),
      '#default_value' => $status,
      '#description' => $this->t('If checked, an articles listing will display at the configurable path below.'),
      '#size' => 60,
    ];

    $form['view_page']['sitenow_articles_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Articles title'),
      '#description' => $this->t('The title for the articles listing. Defaults to <em>News</em>.'),
      '#default_value' => $default['display_options']['title'],
      '#required' => TRUE,
    ];

    $form['view_page']['sitenow_articles_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Articles path'),
      '#description' => $this->t('The base path for the articles listing. Defaults to <em>news</em>.<br /><em>Warning:</em> The RSS feed path is controlled by this setting. {articles path}/feed)'),
      '#default_value' => $display['display_options']['path'],
      '#required' => TRUE,
    ];

    $form['view_page']['sitenow_articles_header_content'] = [
      '#type' => 'text_format',
      '#format' => 'filtered_html',
      '#title' => $this->t('Header Content'),
      '#description' => $this->t('Enter any content that is displayed above the articles listing.'),
      '#default_value' => $default['display_options']['header']['area']['content']['value'],
    ];

    $form['view_page']['sitenow_articles_archive'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display monthly archive'),
      '#default_value' => $show_archive,
      '#description' => $this->t('If checked, a monthly archive listing will display.'),
      '#size' => 60,
    ];

    $form['view_page']['sitenow_articles_feed'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show RSS Feed icon'),
      '#default_value' => $show_feed,
      '#description' => $this->t('If checked, a linked RSS icon will be displayed on the main news page.'),
      '#size' => 60,
    ];

    if ($view->get('status') === FALSE) {
      $this->messenger()->addError($this->t('Articles views page functionality has been disabled. Please contact an administrator.'));
      $form['view_page']['#disabled'] = TRUE;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Check if path already exists.
    $path = $form_state->getValue('sitenow_articles_path');

    // Clean up path first.
    $path = $this->aliasCleaner->cleanString($path);
    $path_exists = $this->aliasRepository->lookupByAlias('/' . $path, 'en');

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
    $status = (int) $form_state->getValue('sitenow_articles_status');
    $show_feed = $form_state->getValue('sitenow_articles_feed');
    $title = $form_state->getValue('sitenow_articles_title');
    $path = $form_state->getValue('sitenow_articles_path');
    $header_content = $form_state->getValue('sitenow_articles_header_content');
    $show_archive = (int) $form_state->getValue('sitenow_articles_archive');

    $config_settings = $this->configFactory->getEditable(static::SETTINGS);

    $config_updates = [
      'show_teaser_link_indicator' => 'show_teaser_link_indicator',
      'featured_image_display_default' => 'featured_image_display_default',
      'tag_display' => 'tag_display',
      'related_display' => 'related_display',
      'preserved_links_message_display' => [
        'preserved_links_message_display',
        'value',
      ],
      'display_articles_by_author' => 'display_articles_by_author',
    ];

    foreach ($config_updates as $config_name => $form_state_value) {
      $config_settings->set($config_name, $form_state->getValue($form_state_value))
        ->save();
    }

    // Clean path.
    $path = $this->aliasCleaner->cleanString($path);

    // Load article listing view.
    $view = $this->entityTypeManager->getStorage('view')->load('articles');
    $display =& $view->getDisplay('page_articles');
    $feed =& $view->getDisplay('feed_articles');
    $archive =& $view->getDisplay('block_articles_archive');
    $default =& $view->getDisplay('default');

    // Enable/Disable view display.
    if ($status === 1) {
      $display['display_options']['enabled'] = TRUE;
    }
    else {
      $display['display_options']['enabled'] = FALSE;
    }

    // Set title.
    $default['display_options']['title'] = $title;
    $feed['display_options']['title'] = $title;

    // Set validated and clean path.
    $display['display_options']['path'] = $path;
    $feed['display_options']['path'] = $path . '/feed';

    $archive['display_options']['arguments']['created_year_month']['summary_options']['base_path'] = $path;

    if ($show_archive === 1) {
      $archive['display_options']['enabled'] = TRUE;
    }
    else {
      $archive['display_options']['enabled'] = FALSE;
    }

    // Set header area content.
    $default['display_options']['header']['area']['content']['value'] = $header_content['value'];

    // Display feed icon.
    if ($show_feed) {
      $feed['display_options']['displays']['page_articles'] = 'page_articles';
    }
    else {
      $feed['display_options']['displays']['page_articles'] = '0';
    }

    $view->save();

    $old_pattern = $this->config('pathauto.pattern.article')->get('pattern');

    $new_pattern = $path . '/[node:created:custom:Y]/[node:created:custom:m]/[node:title]';

    // Only run this potentially expensive process if this setting is changing.
    if ($new_pattern != $old_pattern) {
      // Update article path pattern.
      $this->config('pathauto.pattern.article')
        ->set('pattern', $new_pattern)
        ->save();

      // Load and update article node path aliases.
      $entities = $this->entityTypeManager->getStorage('node')
        ->loadByProperties(['type' => 'article']);

      foreach ($entities as $entity) {
        $this->pathAutoGenerator->updateEntityAlias($entity, 'update');
      }
    }

    parent::submitForm($form, $form_state);

    // Clear cache.
    drupal_flush_all_caches();
  }

}
