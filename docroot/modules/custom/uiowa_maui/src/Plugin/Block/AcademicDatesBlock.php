<?php

namespace Drupal\uiowa_maui\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\link\Plugin\Field\FieldWidget\LinkWidget;
use Drupal\uiowa_core\HeadlineHelper;
use Drupal\uiowa_core\LinkHelper;
use Drupal\uiowa_maui\MauiApi;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a MAUI date list block.
 *
 * @Block(
 *   id = "uiowa_maui_academic_dates",
 *   admin_label = @Translation("Academic dates"),
 *   category = @Translation("Site custom")
 * )
 */
class AcademicDatesBlock extends BlockBase implements ContainerFactoryPluginInterface {
  /**
   * The MAUI API service.
   *
   * @var \Drupal\uiowa_maui\MauiApi
   */
  protected $maui;

  /**
   * The form_builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Override the construction method.
   *
   * @param array $configuration
   *   The block configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\uiowa_maui\MauiApi $maui
   *   The uiowa_maui.api service.
   * @param \Drupal\Core\Form\FormBuilderInterface $formBuilder
   *   The form_builder service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MauiApi $maui, FormBuilderInterface $formBuilder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->maui = $maui;
    $this->formBuilder = $formBuilder;
  }

  /**
   * Override the create method.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The application container.
   * @param array $configuration
   *   The block configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   *
   * @return static
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('uiowa_maui.api'),
      $container->get('form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    $config = $this->getConfiguration();

    [
      $current,
      $plus_one,
      $plus_two,
      $plus_three,
    ] = $this->maui->getSessionsRange($this->maui->getCurrentSession()->id, 3);

    $form['headline'] = HeadlineHelper::getElement([
      'headline' => $config['headline'] ?? NULL,
      'hide_headline' => $config['hide_headline'] ?? 0,
      'heading_size' => $config['heading_size'] ?? 'h2',
      'headline_style' => $config['headline_style'] ?? 'default',
      'headline_alignment' => $config['headline_alignment'] ?? 'default',
      'child_heading_size' => $config['child_heading_size'] ?? 'h3',
    ]);

    $form['headline']['container']['headline']['#description'] = $this->t('Use %session as a placeholder for the selected session below.', [
      '%session' => '@session',
    ]);

    $form['session'] = [
      '#title' => $this->t('Session'),
      '#description' => $this->t('What relative session you wish to display dates for. This will roll over automatically when the session ends. The %exposed option will allow the user to select a session, defaulting to the current.', [
        '@current' => $current->shortDescription,
        '%exposed' => '- Exposed -',
      ]),
      '#type' => 'select',
      '#options' => [
        0 => $this->t('Current session (@session, ends @end)', [
          '@session' => $current->shortDescription,
          '@end' => date('n/j/Y', strtotime($current->endDate)),
        ]),
        1 => $this->t('Current session plus one (@session, ends @end)', [
          '@session' => $plus_one->shortDescription,
          '@end' => date('n/j/Y', strtotime($plus_one->endDate)),
        ]),
        2 => $this->t('Current session plus two (@session, ends @end)', [
          '@session' => $plus_two->shortDescription,
          '@end' => date('n/j/Y', strtotime($plus_two->endDate)),
        ]),
        3 => $this->t('Current session plus three (@session, ends @end)', [
          '@session' => $plus_three->shortDescription,
          '@end' => date('n/j/Y', strtotime($plus_three->endDate)),
        ]),
      ],
      '#default_value' => $config['session'] ?? '',
      '#required' => FALSE,
      '#empty_value' => '',
      '#empty_option' => $this->t('- Exposed -'),
    ];

    $form['category'] = [
      '#type' => 'select',
      '#title' => $this->t('Category'),
      '#description' => $this->t('Select a category to filter dates on. The %exposed option will allow the user to select a category, defaulting to all categories.', [
        '%exposed' => '- Exposed -',
      ]),
      '#default_value' => $config['category'] ?? '',
      '#empty_value' => '',
      '#empty_option' => $this->t('- Exposed -'),
      '#options' => $this->maui->getDateCategories(),
    ];

    $form['limit_dates'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Limit number of dates displayed'),
      '#description' => $this->t('If checked, we recommend including a link to all upcoming dates.'),
      '#default_value' => $config['limit_dates'] ?? FALSE,
      '#return_value' => TRUE,
    ];

    $form['items_to_display'] = [
      '#type' => 'number',
      '#title' => $this->t('Dates to display'),
      '#description' => $this->t('Select the number of dates to display. Minimum of 1 and maximum of 50.'),
      '#default_value' => $config['items_to_display'] ?? 10,
      '#min' => 1,
      '#max' => 50,
      '#process_default_value' => FALSE,
      '#states' => [
        'visible' => [
          [
            "input[name='settings[limit_dates]']" => [
              'checked' => TRUE,
            ],
          ],
        ],
      ],
    ];

    $form['display_more_link'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display more link'),
      '#description' => $this->t('Check to include a "display more" link. Default is https://registrar.uiowa.edu/academic-calendar. Alternatively, a custom URL path can be provided in the ‘Path’ text box below.'),
      '#default_value' => $config['display_more_link'] ?? FALSE,
      '#return_value' => TRUE,
    ];

    $form['more_link'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Path'),
      '#description' => $this->t('Start typing the title of a piece of content to select it. You can also enter an internal path such as /node/add or an external URL such as http://example.com. Enter %front to link to the front page.'),
      '#default_value' => isset($config['more_link']) ? LinkHelper::getUriAsDisplayableString($config['more_link']) : 'https://registrar.uiowa.edu/academic-calendar',
      '#element_validate' => [
        [
          LinkWidget::class,
          'validateUriElement',
        ],
      ],
      // @todo The user should be able to select an entity type. Will be fixed
      // in https://www.drupal.org/node/2423093.
      '#target_type' => 'node',
      // Disable autocompletion when the first character is '/', '#' or '?'.
      '#attributes' => [
        'data-autocomplete-first-character-blacklist' => '/#?',
      ],
      '#process_default_value' => FALSE,
      '#states' => [
        'visible' => [
          [
            "input[name='settings[display_more_link]']" => [
              'checked' => TRUE,
            ],
          ],
        ],
      ],
    ];

    $form['more_text'] = [
      '#type' => 'textfield',
      '#title' => 'Custom text',
      '#default_value' => $config['more_text'] ?? 'View more',
      '#process_default_value' => FALSE,
      '#states' => [
        'visible' => [
          [
            "input[name='settings[display_more_link]']" => [
              'checked' => TRUE,
            ],
          ],
        ],
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockValidate($form, FormStateInterface $form_state) {
    $headline = $form_state->getValue('headline')['container']['headline'];

    if (stristr($headline, '@session')) {
      if ($form_state->getValue('session') === '') {
        $form_state->setErrorByName('session', $this->t('You cannot use the %session headline placeholder with the - Exposed - session option.', [
          '%session' => '@session',
        ]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    // Alter the headline field settings for configuration.
    foreach ($form_state->getValues()['headline']['container'] as $name => $value) {
      $this->configuration[$name] = $value;
    }

    $session = $form_state->getValue('session');
    $category = $form_state->getValue('category');
    $items_to_display = $form_state->getValue('items_to_display');
    $limit_dates = $form_state->getValue('limit_dates');
    $display_more_link = $form_state->getValue('display_more_link');
    $more_link = $form_state->getValue('more_link');
    $more_text = $form_state->getValue('more_text');

    // Convert select list values to what we're expecting in the form builder.
    // Sessions and category need to be NULL'ed out if deselected.
    $this->configuration['session'] = ($session === '') ? NULL : $session;
    $this->configuration['category'] = ($category === '') ? NULL : $category;

    // These can be saved as-is.
    $this->configuration['items_to_display'] = $items_to_display;
    $this->configuration['limit_dates'] = $limit_dates;
    $this->configuration['display_more_link'] = $display_more_link;
    $this->configuration['more_link'] = $more_link;
    $this->configuration['more_text'] = $more_text;

    parent::blockSubmit($form, $form_state);
  }

  /**
   * Build the block.
   */
  public function build() {
    $config = $this->getConfiguration();

    $build = [
      '#attached' => [
        'library' => 'uiowa_maui/session_dates',
      ],
    ];

    // Replace the dynamic placeholder value with the session name.
    if (stristr($config['headline'], '@session')) {
      $bounding = $this->maui->getSessionsBounded(0, 3);
      $current = $bounding[$config['session']];
      $config['headline'] = str_replace('@session', $current->shortDescription, $config['headline']);
    }

    $build['heading'] = [
      '#theme' => 'uiowa_core_headline',
      '#headline' => $config['headline'],
      '#hide_headline' => $config['hide_headline'],
      '#heading_size' => $config['heading_size'],
      '#headline_style' => $config['headline_style'],
      '#headline_alignment' => $config['headline_alignment'] ?? 'default',
    ];

    if (empty($config['headline'])) {
      $child_heading_size = $config['child_heading_size'];
    }
    else {
      $child_heading_size = HeadlineHelper::getHeadingSizeUp($config['heading_size']);
    }

    $build['form'] = $this->formBuilder->getForm(
      '\Drupal\uiowa_maui\Form\AcademicDatesForm',
      $config['session'],
      $config['category'],
      $child_heading_size,
      $config['items_to_display'],
      $config['limit_dates'],
    );

    if ($config['display_more_link'] === TRUE) {
      $more_link = $config['more_link'] ?? 'https://registrar.uiowa.edu/academic-calendar';

      $build['more_link'] = [
        '#title' => $this->t('@more_text', [
          '@more_text' => $config['more_text'] ?? 'View more',
        ]),
        '#type' => 'link',
        '#url' => Url::fromUri($more_link),
        '#attributes' => [
          'class' => ['bttn', 'bttn--primary', 'more-link'],
        ],
      ];
    }

    return $build;
  }

}
