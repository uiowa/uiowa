<?php

namespace Drupal\uiowa_profiles\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\Core\Url;
use Drupal\pathauto\AliasCleanerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Profiles settings for this site.
 */
class SettingsForm extends ConfigFormBase {
  /**
   * The pathauto.alias_cleaner service.
   *
   * @var \Drupal\pathauto\AliasCleanerInterface
   */
  protected $aliasCleaner;

  /**
   * The path.validator service.
   *
   * @var \Drupal\Core\Path\PathValidatorInterface
   */
  protected $pathValidator;

  /**
   * The route.builder service.
   *
   * @var \Drupal\Core\Routing\RouteBuilderInterface
   */
  protected $routeBuilder;

  /**
   * Settings form constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config.factory service.
   * @param \Drupal\pathauto\AliasCleanerInterface $aliasCleaner
   *   The pathauto.alias_cleaner service.
   * @param \Drupal\Core\Path\PathValidatorInterface $pathValidator
   *   The path.validator service.
   * @param \Drupal\Core\Routing\RouteBuilderInterface $routeBuilder
   *   The route.builder service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, AliasCleanerInterface $aliasCleaner, PathValidatorInterface $pathValidator, RouteBuilderInterface $routeBuilder) {
    parent::__construct($config_factory);
    $this->aliasCleaner = $aliasCleaner;
    $this->pathValidator = $pathValidator;
    $this->routeBuilder = $routeBuilder;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('pathauto.alias_cleaner'),
      $container->get('path.validator'),
      $container->get('router.builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'uiowa_profiles_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['uiowa_profiles.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key'),
      '#default_value' => $this->config('uiowa_profiles.settings')->get('api_key'),
      '#description' => $this->t('The API key provided by the ITS-AIS Profiles team.'),
      '#required' => TRUE,
    ];

    $form['directory_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Directory Path'),
      '#default_value' => $this->config('uiowa_profiles.settings')->get('directory.path') ?? '/profiles/people',
      '#description' => $this->t('The path for the Profiles directory. Serves as the base for all profiles and an additional <a href=":url">sitemap</a> to submit to search engines.', [
        ':url' => Url::fromRoute('uiowa_profiles.sitemap')->toString(),
      ]),
      '#required' => TRUE,
    ];

    $form['directory_canonical'] = [
      '#type' => 'url',
      '#title' => $this->t('Canonical Link Base URL'),
      '#default_value' => $this->config('uiowa_profiles.settings')->get('directory.canonical') ?? '',
      '#description' => $this->t('The Base URL to generate the canonical link to a profile for SEO. Leave blank if this site is the canonical source.'),
      '#required' => FALSE,
      '#placeholder' => $this->getRequest()->getSchemeAndHttpHost(),
    ];

    $form['directory_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Directory Title'),
      '#default_value' => $this->config('uiowa_profiles.settings')->get('directory.title') ?? 'People',
      '#description' => $this->t('The page title to display on the Profiles directory.'),
      '#required' => TRUE,
    ];

    $form['directory_page_size'] = [
      '#type' => 'number',
      '#title' => $this->t('Directory Page Size'),
      '#default_value' => $this->config('uiowa_profiles.settings')->get('directory.page_size') ?? 10,
      '#min' => 5,
      '#max' => 50,
      '#description' => $this->t('Number of entries per page of the directory. Min: 5, Max: 50'),
      '#required' => TRUE,
    ];

    $intro = $this->config('uiowa_profiles.settings')->get('directory.intro');

    $form['directory_intro'] = [
      '#type' => 'text_format',
      '#rows' => '10',
      '#cols' => '100',
      '#title' => $this->t('Introduction'),
      '#format' => 'filtered_html',
      '#allowed_formats' => [
        'filtered_html',
      ],
      '#default_value' => $intro['value'] ?? '',
      '#description' => $this->t('Introductory text to be included at the top of the Profiles directory.'),
      '#required' => FALSE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritDoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $fields = [
      'directory_path',
    ];

    foreach ($fields as $field) {
      $path = $this->aliasCleaner->cleanAlias($form_state->getValue($field));

      /** @var \Drupal\Core\Url $url */
      $url = $this->pathValidator->getUrlIfValid($path);

      // If $url is anything besides FALSE then the path is already in use. We
      // also check if the route belongs to another module.
      if ($url && !str_starts_with($url->getRouteName(), 'uiowa_profiles')) {
        $form_state->setErrorByName($field, 'This path is already in use.');
      }
      else {
        $form_state->setValue($field, $path);
      }
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('uiowa_profiles.settings')
      ->set('api_key', $form_state->getValue('api_key'))
      ->set('directory.path', $form_state->getValue('directory_path'))
      ->set('directory.canonical', $form_state->getValue('directory_canonical'))
      ->set('directory.title', $form_state->getValue('directory_title'))
      ->set('directory.page_size', $form_state->getValue('directory_page_size'))
      ->set('directory.intro', $form_state->getValue('directory_intro'))
      ->save();

    parent::submitForm($form, $form_state);

    // Rebuild routes so any path changes are applied.
    $this->routeBuilder->rebuild();
  }

}
