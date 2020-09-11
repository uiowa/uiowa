<?php

namespace Drupal\sitenow_articles\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\path_alias\AliasRepositoryInterface;
use Drupal\pathauto\AliasCleanerInterface;
use Drupal\pathauto\PathautoGenerator;
use Drupal\views\Entity\View;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure UIowa Articles settings for this site.
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
    return 'sitenow_articles_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'sitenow_articles.settings',
      'pathauto.pattern.article',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $view = View::load('articles');
    $display =& $view->getDisplay('page_articles');
    $archive =& $view->getDisplay('block_articles_archive');
    $feed =& $view->getDisplay('feed_articles');

    if ($feed["display_options"]["displays"]["page_articles"] == 'page_articles') {
      $show_feed = 1;
    }
    else {
      $show_feed = 0;
    }
    if ($archive["display_options"]["enabled"] == TRUE) {
      $show_archive = 1;
    }
    else {
      $show_archive = 0;
    }
    $default =& $view->getDisplay('default');
    if ($display["display_options"]["enabled"] == TRUE) {
      $status = 1;
    }
    else {
      $status = 0;
    }

    $form['markup'] = [
      '#type' => 'markup',
      '#markup' => $this->t('<p>These settings allows you to customize the display of articles on the site.</p>'),
    ];

    $form['global'] = [
      '#type' => 'fieldset',
      '#title' => 'Settings',
      '#collapsible' => FALSE,
    ];

    $form['global']['sitenow_articles_status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable articles listing'),
      '#default_value' => $status,
      '#description' => $this->t('If checked, an articles listing will display at the configurable path below.'),
      '#size' => 60,
    ];

    $form['global']['sitenow_articles_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Articles title'),
      '#description' => $this->t('The title for the articles listing. Defaults to <em>News</em>.'),
      '#default_value' => $default['display_options']['title'],
      '#required' => TRUE,
    ];

    $form['global']['sitenow_articles_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Articles path'),
      '#description' => $this->t('The base path for the articles listing. Defaults to <em>news</em>.<br /><em>Warning:</em> The RSS feed path is controlled by this setting. {articles path}/feed)'),
      '#default_value' => $display['display_options']['path'],
      '#required' => TRUE,
    ];

    $form['global']['sitenow_articles_header_content'] = [
      '#type' => 'text_format',
      '#format' => 'filtered_html',
      '#title' => $this->t('Header Content'),
      '#description' => $this->t('Enter any content that is displayed above the articles listing.'),
      '#default_value' => $default["display_options"]["header"]["area"]["content"]["value"],
    ];

    $form['global']['sitenow_articles_archive'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display monthly archive'),
      '#default_value' => $show_archive,
      '#description' => $this->t('If checked, a monthly archive listing will display.'),
      '#size' => 60,
    ];

    $form['global']['sitenow_articles_feed'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show RSS Feed icon'),
      '#default_value' => $show_feed,
      '#description' => $this->t('If checked, a linked RSS icon will be displayed.'),
      '#size' => 60,
    ];

    if ($view->get('status') == FALSE) {
      $this->messenger()->addError($this->t('Related functionality has been turned off. Please contact an administrator.'));
      $form['#disabled'] = TRUE;
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
    $status = $form_state->getValue('sitenow_articles_status');
    $show_feed = $form_state->getValue('sitenow_articles_feed');
    $title = $form_state->getValue('sitenow_articles_title');
    $path = $form_state->getValue('sitenow_articles_path');
    $header_content = $form_state->getValue('sitenow_articles_header_content');
    $show_archive = $form_state->getValue('sitenow_articles_archive');
    ;

    // Clean path.
    $path = $this->aliasCleaner->cleanString($path);

    // Load article listing view.
    $view = View::load('articles');
    $display =& $view->getDisplay('page_articles');
    $feed =& $view->getDisplay('feed_articles');
    $archive =& $view->getDisplay('block_articles_archive');
    $default =& $view->getDisplay('default');

    // Enable/Disable view display.
    if ($status == 1) {
      $display["display_options"]["enabled"] = TRUE;
    }
    else {
      $display["display_options"]["enabled"] = FALSE;
    }

    // Set title.
    $default["display_options"]["title"] = $title;
    $feed["display_options"]["title"] = $title;

    // Set validated and clean path.
    $display['display_options']['path'] = $path;
    $feed['display_options']['path'] = $path . '/feed';

    $archive["display_options"]["arguments"]["created_year_month"]["summary_options"]["base_path"] = $path;

    if ($show_archive == 1) {
      $archive["display_options"]["enabled"] = TRUE;
    }
    else {
      $archive["display_options"]["enabled"] = FALSE;
    }

    // Set header area content.
    $default['display_options']['header']['area']['content']['value'] = $header_content['value'];

    // Display feed icon.
    if ($show_feed) {
      $feed["display_options"]["displays"]["page_articles"] = 'page_articles';
    }
    else {
      $feed["display_options"]["displays"]["page_articles"] = '0';
    }
    $view->save();

    // Update article path pattern.
    $this->config('pathauto.pattern.article')->set('pattern', $path . '/[node:created:custom:Y]/[node:created:custom:m]/[node:title]')->save();

    // Load and update article node path aliases.
    $entities = $this->entityTypeManager->getStorage('node')->loadByProperties(['type' => 'article']);

    foreach ($entities as $entity) {
      $this->pathAutoGenerator->updateEntityAlias($entity, 'update');
    }

    parent::submitForm($form, $form_state);

    // Clear cache.
    drupal_flush_all_caches();
  }

}
