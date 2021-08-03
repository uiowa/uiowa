<?php

namespace Drupal\uiowa_profiles\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\Core\Routing\RouteProviderInterface;
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
   * The router.route_provider service.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected $routeProvider;

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
   *   The router.builder service.
   * @param \Drupal\Core\Routing\RouteProviderInterface $routeProvider
   *   The router.route_provider service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, AliasCleanerInterface $aliasCleaner, PathValidatorInterface $pathValidator, RouteBuilderInterface $routeBuilder, RouteProviderInterface $routeProvider) {
    parent::__construct($config_factory);
    $this->aliasCleaner = $aliasCleaner;
    $this->pathValidator = $pathValidator;
    $this->routeBuilder = $routeBuilder;
    $this->routeProvider = $routeProvider;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('pathauto.alias_cleaner'),
      $container->get('path.validator'),
      $container->get('router.builder'),
      $container->get('router.route_provider')
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

    $form['#tree'] = TRUE;

    $form['profiles_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Directories'),
      '#prefix' => '<div id="profiles-fieldset">',
      '#suffix' => '</div>',
      '#attached' => [
        'library' => 'uiowa_profiles/settings-form',
      ],
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
        'aria-label' => 'Profiles directory tabs',
      ],
    ];

    $directories = $form_state->getValue([
      'profiles_fieldset',
      'tabs_container',
      'directories',
    ]) ?? $config->get('directories') ?? [];

    // We need to keep count of the directories for later so that we can determine if we need the delete button or not.
    $count = count($directories);

    // For each directory...
    foreach ($directories as $key => $directory) {
      // Detect if this is the first tab, we will use this for the tabindex setting later.
      $is_first_tab = ($key === array_key_first($directories));

      $form['profiles_fieldset']['tabs_container']['tablist']['tab-button-' . $key] = [
        '#type' => 'button',
        // On this tab button, if the title is not empty, set the text to the title.
        //If it is empty, set it to 'People-' + $key + 1.
        '#value' => !empty($directory['title']) ? $directory['title'] : 'People-' . strval($key + 1),
        '#attributes' => [
          'role' => 'tab',
          'aria-selected' => $is_first_tab ? 'true' : 'false',
          'aria-controls' => 'profiles-directory-fieldset-' . $key,
          'id' => 'tab-' . $key,
          // If this is the first tab button, make sure it has the correct tabindex for accessibility.
          'tabindex' => $is_first_tab ? 0 : -1,
          // We dont want this tab button to submit the form, so we set it to `return(false)` on click.
          'onclick' => 'return (false);',
        ],
      ];

      $form['profiles_fieldset']['tabs_container']['directories'][$key] = [
        '#type' => 'fieldset',
        '#attributes' => [
          'id' => 'profiles-directory-fieldset-' . $key,
          'class' => ['profiles-directory-fieldset'],
          'role' => 'tabpanel',
          'tabindex' => 0,
          'aria-labelledby' => 'tab-' . $key,
        ],
      ];

      // If it is not the first tab, we want to hide the contents, because the first tab is focused/open.
      if (!$is_first_tab) {
        $form['profiles_fieldset']['tabs_container']['directories'][$key]['#attributes']['hidden'] = 'true';
      }

      $form['profiles_fieldset']['tabs_container']['directories'][$key]['title'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Title'),
        //If the title doesn't exist, default it to 'People'.
        '#default_value' => !empty($directory['title']) ? $directory['title'] : 'People',
        '#description' => $this->t('The page title to display on the Profiles directory.'),
        '#required' => TRUE,
        '#attributes' => [
          'class' => [
            'profiles-fieldset-title',
          ],
          'data-profiles-fieldset-title-index' => $key,
        ],
      ];

      $form['profiles_fieldset']['tabs_container']['directories'][$key]['api_key'] = [
        '#type' => 'textfield',
        '#title' => $this->t('API Key'),
        //If the API key doesn't exist, default it to ''.
        '#default_value' => $directory['api_key'] ?? '',
        '#description' => $this->t('The API key provided by the ITS-AIS Profiles team.'),
        '#required' => TRUE,
      ];

      $form['profiles_fieldset']['tabs_container']['directories'][$key]['path'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Path'),
        //If the API key doesn't exist, default it to ''.
        '#default_value' => $directory['path'] ?? '',
        '#description' => $this->t('The path for the Profiles directory. Serves as the base for all profiles.'),
        '#required' => TRUE,
      ];

      // Add link to sitemap if route exists, i.e. the form has been saved.
      $route = "uiowa_profiles.sitemap.{$key}";
      $exists = count($this->routeProvider->getRoutesByNames([$route])) === 1;

      if ($exists) {
        $form['profiles_fieldset']['tabs_container']['directories'][$key]['path']['#description'] .= $this->t('&nbsp;It also creates an additional <a href=":url">sitemap</a> to submit to search engines.', [
          ':url' => Url::fromRoute($route)->toString(),
        ]);
      }

      $form['profiles_fieldset']['tabs_container']['directories'][$key]['page_size'] = [
        '#type' => 'number',
        '#title' => $this->t('Page Size'),
        //If the page size doesn't exist, default it to 10.
        '#default_value' => $directory['page_size'] ?? 10,
        '#min' => 5,
        '#max' => 50,
        '#description' => $this->t('Number of entries per page of the directory. Min: 5, Max: 50'),
        '#required' => TRUE,
      ];

      $intro = $directory['intro'];

      $form['profiles_fieldset']['tabs_container']['directories'][$key]['intro'] = [
        '#type' => 'text_format',
        '#rows' => '10',
        '#cols' => '100',
        '#title' => $this->t('Introduction'),
        '#format' => 'filtered_html',
        '#allowed_formats' => [
          'filtered_html',
        ],
        //If the intro doesn't exist, default it to ''.
        '#default_value' => $intro['value'] ?? '',
        '#description' => $this->t('Introductory text to be included at the top of the directory.'),
        '#required' => FALSE,
      ];

      if ($count > 1) {
        // The #value is important here. It is used to differentiate the
        // form action so that the triggering element is set correctly.
        $form['profiles_fieldset']['tabs_container']['directories'][$key]['delete'] = [
          '#type' => 'submit',
          '#value' => $this->t('Delete @directory', [
            '@directory' => $directory['title'],
          ]),
          '#submit' => ['::removeSubmit'],
          '#ajax' => [
            'callback' => '::addRemoveCallback',
            'wrapper' => 'profiles-fieldset',
          ],
          '#attributes' => [
            'data-directory-index' => $key,
            'class' => [
              'delete-profiles-instance',
            ],
          ],
        ];
      }
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];

    // This button allows a user to add another directory instance.
    $form['profiles_fieldset']['tabs_container']['tablist']['add'] = [
      '#type' => 'submit',
      '#value' => $this->t('+'),
      '#submit' => ['::addSubmit'],
      '#ajax' => [
        'callback' => '::addRemoveCallback',
        'wrapper' => 'profiles-fieldset',
      ],
      '#attributes' => [
        'class' => [
          'button',
          'add-directory',
        ],
        'aria-selected' => 'false',
      ],
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * AJAX callback for the add and remove buttons.
   */
  public function addRemoveCallback(array &$form, FormStateInterface $form_state) {
    return $form['profiles_fieldset'];
  }

  /**
   * {@inheritdoc}
   */
  public function addSubmit(array &$form, FormStateInterface $form_state) {
    $directories = $form_state->getValue([
      'profiles_fieldset',
      'tabs_container',
      'directories',
    ]);

    $directories[] = [];
    $form_state->setValue(['profiles_fieldset', 'tabs_container', 'directories'], $directories);
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function removeSubmit(array &$form, FormStateInterface $form_state) {
    $delete = $form_state->getTriggeringElement()['#attributes']['data-directory-index'];
    $directories = $form_state->getValue([
      'profiles_fieldset',
      'tabs_container',
      'directories',
    ]);

    unset($directories[$delete]);
    $form_state->setValue(['profiles_fieldset', 'tabs_container', 'directories'], $directories);
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $directories = [];

    foreach ($form_state->getValue([
      'profiles_fieldset',
      'tabs_container',
      'directories',
    ]) as $directory) {
      unset($directory['delete']);
      $directories[] = $directory;
    }

    $this->config('uiowa_profiles.settings')
      ->set('directories', $directories)
      ->save();

    parent::submitForm($form, $form_state);

    // Rebuild routes so any path changes are applied.
    $this->routeBuilder->rebuild();
  }

  /**
   * {@inheritDoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $directories = $form_state->getValue([
      'profiles_fieldset',
      'tabs_container',
      'directories',
    ]);

    // Get all the directory paths as an array.
    $paths = array_map(function ($v) {
      return $v['path'];
    }, $directories);

    // Count how many times each path occurs and mark it as a duplicate if > 1.
    $dups = [];

    foreach (array_count_values($paths) as $val => $c) {
      if ($c > 1) {
        $dups[] = $val;
      }
    }

    foreach ($directories as $key => $directory) {
      $path = $this->aliasCleaner->cleanAlias($directory['path']);

      /** @var \Drupal\Core\Url $url */
      $url = $this->pathValidator->getUrlIfValid($path);

      // If $url is anything besides FALSE then the path is already in use. We
      // also check if the route belongs to another module or is a duplicate
      // of another directory.
      if ($url &&
        !str_starts_with($url->getRouteName(), 'uiowa_profiles')) {
        $form_state->setErrorByName("profiles_fieldset][tabs_container][directories][{$key}][path", 'This path is already in use.');
      }
      if (in_array($path, $dups)) {
        $form_state->setErrorByName("profiles_fieldset][tabs_container][directories][{$key}][path", 'This path is a duplicate of another directory path.');
      }
      else {
        $form_state->setValue([
          'profiles_fieldset',
          'tabs_container',
          'directories',
          $key,
          'path',
        ], $path);
      }
    }

    parent::validateForm($form, $form_state);
  }

}
