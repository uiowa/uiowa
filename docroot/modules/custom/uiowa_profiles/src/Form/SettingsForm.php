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
    $config = $this->config('uiowa_profiles.settings');

    $form['description'] = array(
      '#markup' => '<div>'. t('This example shows an add-more and a remove-last button.').'</div>',
    );

    $form['#tree'] = TRUE;
    $form['profiles_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('PEOPLE COMING TO THE PICNIC'),
      '#prefix' => '<div id="profiles-fieldset">',
      '#suffix' => '</div>',
      '#attached' => [
        'library' => 'uiowa_profiles/settings-form'
      ]
    ];

    $form['profiles_fieldset']['tabs_container'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['tabs'],
      ],
    ];

    $form['profiles_fieldset']['tabs_container']['tablist'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['tabs--list'],
        'role' => 'tablist',
        'aria-label' => 'Profiles instance tabs'
      ],
    ];

    $num_prof_instances = ($form_state->get('num_prof_instances')) ? $form_state->get('num_prof_instances') : $config->get('num_prof_instances');
    if ($num_prof_instances === NULL) {
      $form_state->set('num_prof_instances', 1);
      $num_prof_instances = 1;
    }
    else {
      $form_state->set('num_prof_instances', $num_prof_instances);
    }

    for ($i = 0; $i < $num_prof_instances; $i++) {
      $instance_values = $config->get()[$i];
      $is_first_tab = $i === 0;

      $form['profiles_fieldset']['tabs_container']['tablist']['tab-button-' . $i] = [
        '#type' => 'button',
        '#value' => !empty($instance_values['directory_title']) ? $instance_values['directory_title'] : 'People-' . $i,
        '#attributes' => [
          'role' => 'tab',
          'aria-selected' => $is_first_tab ? 'true' : 'false',
          'aria-controls' => 'profiles-instance-fieldset-' . $i,
          'id' => 'tab-' . $i,
          'tabindex' => $is_first_tab ? 0 : -1,
          'onclick' => 'return (false);',
        ],
      ];

      $form['profiles_fieldset']['tabs_container']['instances'][$i]['profiles_instance'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Profiles instance'),
        '#attributes' => [
          'id' => 'profiles-instance-fieldset-' . $i,
          'class' => ['profiles-instance-fieldset'],
          'role' => 'tabpanel',
          'tabindex' => 0,
          'aria-labelledby' => 'tab-' . $i,
        ],
      ];

      if (!$is_first_tab) {
        $form['profiles_fieldset']['tabs_container']['instances'][$i]['profiles_instance']['#attributes']['hidden'] = 'true';
      }

      $form['profiles_fieldset']['tabs_container']['instances'][$i]['profiles_instance']['directory_title'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Directory Title'),
        '#default_value' => !empty($instance_values['directory_title']) ? $instance_values['directory_title'] : 'People',
        '#description' => $this->t('The page title to display on the Profiles directory.'),
        '#required' => TRUE,
      ];

      $form['profiles_fieldset']['tabs_container']['instances'][$i]['profiles_instance']['api_key'] = [
        '#type' => 'textfield',
        '#title' => $this->t('API Key'),
        '#default_value' => $instance_values['api_key'] ?? '',
        '#description' => $this->t('The API key provided by the ITS-AIS Profiles team.'),
        '#required' => TRUE,
      ];

      $form['profiles_fieldset']['tabs_container']['instances'][$i]['profiles_instance']['directory_path'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Directory Path'),
        '#default_value' => $instance_values['directory_path'] ?? '/profiles/people',
        '#description' => $this->t('The path for the Profiles directory. Serves as the base for all profiles and an additional <a href=":url">sitemap</a> to submit to search engines.', [
          // @TODO: is this the right thing we are getting here for multiple directory instances?
          ':url' => Url::fromRoute('uiowa_profiles.sitemap')->toString(),
        ]),
        '#required' => TRUE,
      ];

      $form['profiles_fieldset']['tabs_container']['instances'][$i]['profiles_instance']['directory_canonical'] = [
        '#type' => 'url',
        '#title' => $this->t('Canonical Link Base URL'),
        '#default_value' => $instance_values['directory_canonical'] ?? '',
        '#description' => $this->t('The Base URL to generate the canonical link to a profile for SEO. Leave blank if this site is the canonical source.'),
        '#required' => FALSE,
        '#placeholder' => $this->getRequest()->getSchemeAndHttpHost(),
      ];

      $form['profiles_fieldset']['tabs_container']['instances'][$i]['profiles_instance']['directory_page_size'] = [
        '#type' => 'number',
        '#title' => $this->t('Directory Page Size'),
        '#default_value' => $instance_values['directory_page_size'] ?? 10,
        '#min' => 5,
        '#max' => 50,
        '#description' => $this->t('Number of entries per page of the directory. Min: 5, Max: 50'),
        '#required' => TRUE,
      ];

//      $intro = $instance_values['directory.intro'];

      $form['profiles_fieldset']['tabs_container']['instances'][$i]['profiles_instance']['directory_intro'] = [
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
    }
    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['profiles_fieldset']['tabs_container']['tablist']['add_instance']  = [
      '#type' => 'submit',
      '#value' => t('Add one more'),
      '#submit' => array('::addOne'),
      '#ajax' => [
        'callback' => '::addmoreCallback',
        'wrapper' => 'profiles-fieldset',
      ],
      '#attributes' => [
        'class' => [
          'button',
          'add-instance'
        ],
        'aria-selected' => 'false',
      ],
    ];

    if ($num_prof_instances > 1) {
      $form['profiles_fieldset']['actions']['remove_instance'] = [
        '#type' => 'submit',
        '#value' => t('Remove one'),
        '#submit' => array('::removeCallback'),
        '#ajax' => [
          'callback' => '::addmoreCallback',
          'wrapper' => 'profiles-fieldset',
        ]
      ];
    }
    $form_state->setCached(FALSE);
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function addOne(array &$form, FormStateInterface $form_state) {
    $profiles_field = $form_state->get('num_prof_instances');
    $add_button = $profiles_field + 1;
    $form_state->set('num_prof_instances', $add_button);
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function addmoreCallback(array &$form, FormStateInterface $form_state) {
    $profiles_field = $form_state->get('num_prof_instances');
    return $form['profiles_fieldset'];
  }

  /**
   * {@inheritdoc}
   */
  public function removeCallback(array &$form, FormStateInterface $form_state) {
    $profiles_field = $form_state->get('num_prof_instances');
    if ($profiles_field > 1) {
      $remove_button = $profiles_field - 1;
      $form_state->set('num_prof_instances', $remove_button);
    }
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $profiles_fieldset = $form_state->getValue('profiles_fieldset')['tabs_container']['instances'];
    foreach ($profiles_fieldset as $key => $profiles_instance) {

      $this->config('uiowa_profiles.settings')
      ->set($key, $profiles_instance['profiles_instance'])
      ->set('num_prof_instances', $form_state->get('num_prof_instances'))
      ->save();
    }

    parent::submitForm($form, $form_state);
//
//    // Rebuild routes so any path changes are applied.
//    $this->routeBuilder->rebuild();
  }








//  /**
//   * {@inheritDoc}
//   */
//  public function validateForm(array &$form, FormStateInterface $form_state) {
//    $fields = [
//      'directory_path',
//    ];
//
//    foreach ($fields as $field) {
//      $path = $this->aliasCleaner->cleanAlias($form_state->getValue($field));
//
//      /** @var \Drupal\Core\Url $url */
//      $url = $this->pathValidator->getUrlIfValid($path);
//
//      // If $url is anything besides FALSE then the path is already in use. We
//      // also check if the route belongs to another module.
//      if ($url && !str_starts_with($url->getRouteName(), 'uiowa_profiles')) {
//        $form_state->setErrorByName($field, 'This path is already in use.');
//      }
//      else {
//        $form_state->setValue($field, $path);
//      }
//    }
//
//    parent::validateForm($form, $form_state);
//  }

}
