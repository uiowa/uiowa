<?php

namespace Drupal\uiowa_apr\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\pathauto\AliasCleanerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure APR settings for this site.
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
    return 'uiowa_apr_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['uiowa_apr.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key'),
      '#default_value' => $this->config('uiowa_apr.settings')->get('api_key'),
      '#description' => $this->t('The API key provided by the ITS-AIS APR team.'),
      '#required' => TRUE,
    ];

    $form['directory_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Directory Path'),
      '#default_value' => $this->config('uiowa_apr.settings')->get('directory.path') ?? '/apr/people',
      '#description' => $this->t("Path for the site's primary APR directory. Serves as the base for all profile URLs."),
      '#required' => TRUE,
    ];

    $form['directory_canonical'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Canonical Link Base URL'),
      '#default_value' => $this->config('uiowa_apr.settings')->get('directory.canonical') ?? '',
      '#description' => $this->t('The Base URL to generate the canonical link to a profile for SEO. Trailing slash should be included. Leave blank if this site is the canonical source.'),
      '#required' => FALSE,
      '#placeholder' => $this->getRequest()->getHost(),
    ];

    $form['directory_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Directory Title'),
      '#default_value' => $this->config('uiowa_apr.settings')->get('directory.title') ?? 'People',
      '#description' => $this->t("Title for the site's primary APR directory. Will be set as Drupal's page title."),
      '#required' => TRUE,
    ];

    $form['directory_page_size'] = [
      '#type' => 'number',
      '#title' => $this->t('Directory Page Size'),
      '#default_value' => $this->config('uiowa_apr.settings')->get('directory.page_size') ?? 30,
      '#min' => 5,
      '#max' => 50,
      '#description' => $this->t('Number of entries per page of the directory. Min: 5, Max: 50'),
      '#required' => TRUE,
    ];

    $intro = $this->config('uiowa_apr.settings')->get('directory.intro');

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
      '#description' => $this->t('HTML to be included at top of directory. Will be enclosed in a <em>div</em> element with the class apr-directory-introduction.'),
      '#required' => FALSE,
    ];

    $form['directory_show_switcher'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show List Switcher'),
      '#return_value' => TRUE,
      '#default_value' => $this->config('uiowa_apr.settings')->get('directory.show_switcher') ?? FALSE,
      '#description' => $this->t('Flag to show or hide the control that allows the user to switch between list views.'),
    ];

    $form['publications_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Publications Path'),
      '#default_value' => $this->config('uiowa_apr.settings')->get('publications.path') ?? '/apr/publications',
      '#description' => $this->t("Path for the site's APR publications directory."),
      '#required' => TRUE,
    ];

    $form['publications_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Publications Title'),
      '#default_value' => $this->config('uiowa_apr.settings')->get('publications.title') ?? 'Research',
      '#description' => $this->t("Page title for the site's primary publications directory."),
      '#required' => TRUE,
    ];

    $form['publications_page_size'] = [
      '#type' => 'number',
      '#title' => $this->t('Publications Page Size'),
      '#default_value' => $this->config('uiowa_apr.settings')->get('publications.page_size') ?? 10,
      '#min' => 5,
      '#max' => 50,
      '#description' => $this->t('Number of entries per page of the publications directory. Min: 5, Max: 50.'),
      '#required' => TRUE,
    ];

    $form['publications_departments'] = [
      '#type' => 'textarea',
      '#rows' => '5',
      '#cols' => '100',
      '#title' => $this->t('Publications Departments'),
      '#default_value' => $this->config('uiowa_apr.settings')->get('publications.departments') ?? '',
      '#description' => $this->t('Customize the list of departments exposed by the publications tool. Expects a JSON array. Value attributes in the array must match a department name in APR. Use the text attribute to customize the text the user will see.'),
      '#attributes' => ['placeholder' => "[{text: 'Economics', value: 'Economics'}]"],
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
      'publications_path',
    ];

    foreach ($fields as $field) {
      $path = $this->aliasCleaner->cleanAlias($form_state->getValue($field));

      /** @var \Drupal\Core\Url $url */
      $url = $this->pathValidator->getUrlIfValid($path);

      // If $url is anything besides FALSE then the path is already in use.
      if ($url && substr($url->getRouteName(), 0, 9) != 'uiowa_apr') {
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
    $this->config('uiowa_apr.settings')
      ->set('api_key', $form_state->getValue('api_key'))
      ->set('directory.path', $form_state->getValue('directory_path'))
      ->set('directory.canonical', $form_state->getValue('directory_canonical'))
      ->set('directory.title', $form_state->getValue('directory_title'))
      ->set('directory.page_size', $form_state->getValue('directory_page_size'))
      ->set('directory.intro', $form_state->getValue('directory_intro'))
      ->set('directory.show_switcher', $form_state->getValue('directory_show_switcher'))
      ->set('publications.path', $form_state->getValue('publications_path'))
      ->set('publications.title', $form_state->getValue('publications_title'))
      ->set('publications.page_size', $form_state->getValue('publications_page_size'))
      ->set('publications.departments', $form_state->getValue('publications_departments'))
      ->save();

    parent::submitForm($form, $form_state);

    // Rebuild routes so any path changes are applied.
    $this->routeBuilder->rebuild();
  }

}
