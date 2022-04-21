<?php

namespace Drupal\uiowa_maui\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\link\Plugin\Field\FieldWidget\LinkWidget;
use Drupal\uiowa_core\HeadlineHelper;
use Drupal\uiowa_maui\MauiApi;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a MAUI date list block.
 *
 * @Block(
 *   id = "uiowa_maui_academic_dates",
 *   admin_label = @Translation("Academic dates"),
 *   category = @Translation("MAUI")
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

    $form['display_deadlines'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display upcoming dates'),
      '#description' => $this->t('If this is unchecked all upcoming dates for the session and category will display.'),
      '#default_value' => $config['display_deadlines'] ?? 0,
      '#return_value' => 1,
    ];

    $form['items_to_display'] = [
      '#type' => 'number',
      '#title' => $this->t('Dates to display'),
      '#description' => $this->t('Select the number of dates to display.'),
      '#default_value' => $config['items_to_display'] ?? 10,
      '#min' => 1,
      '#states' => [
        'visible' => [
          [
            "input[name='settings[display_deadlines]']" => [
              'checked' => TRUE,
            ],
          ],
        ],
      ],
    ];

    $form['display_more_link'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Path'),
      '#description' => $this->t('The URL of where the more link should go. This defaults to registrar.uiowa.edu but a custom URL path can be provided. Start typing the title of a piece of content to select it. You can also enter an internal path such as /node/add or an external URL such as http://example.com.'),
      '#default_value' => isset($config['display_more_link']) ? static::getUriAsDisplayableString($config['display_more_link']) : 'https://registrar.uiowa.edu/',
      '#element_validate' => [
        [
          LinkWidget::class,
          'validateUriElement',
        ],
      ],
      // @todo The user should be able to select an entity type. Will be fixed
      //   in https://www.drupal.org/node/2423093.
      '#target_type' => 'node',
      // Disable autocompletion when the first character is '/', '#' or '?'.
      '#attributes' => [
        'data-autocomplete-first-character-blacklist' => '/#?',
      ],
      '#process_default_value' => FALSE,
      '#states' => [
        'visible' => [
          [
            "input[name='settings[display_deadlines]']" => [
              'checked' => TRUE,
            ],
          ],
        ],
      ],
    ];

    $form['display_more_text'] = [
      '#type' => 'textfield',
      '#title' => 'Custom text',
      '#default_value' => $config['display_more_text'] ?? 'View more',
      '#process_default_value' => FALSE,
      '#states' => [
        'visible' => [
          [
            "input[name='settings[display_deadlines]']" => [
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

    // Convert select list values to what we're expecting in the form builder.
    $session = $form_state->getValue('session');
    $category = $form_state->getValue('category');
    $items_to_display = $form_state->getValue('items_to_display');
    $display_deadlines = $form_state->getValue('display_deadlines');
    $display_more_link = $form_state->getValue('display_more_link');
    $display_more_text = $form_state->getValue('display_more_text');

    $this->configuration['session'] = ($session === '') ? NULL : $session;
    $this->configuration['category'] = ($category === '') ? NULL : $category;
    $this->configuration['items_to_display'] = ($items_to_display === '') ? NULL : $items_to_display;
    $this->configuration['display_deadlines'] = ($display_deadlines === '') ? NULL : $display_deadlines;
    $this->configuration['display_more_link'] = ($display_more_link === '') ? NULL : $display_more_link;
    $this->configuration['display_more_text'] = ($display_more_text === '') ? NULL : $display_more_text;
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
      $config['display_deadlines'],
      $config['display_more_link'],
      $config['display_more_text'],

    );
    return $build;
  }

  /**
   * Gets the URI without the 'internal:' or 'entity:' scheme.
   *
   * This method is copied from
   * Drupal\link\Plugin\Field\FieldWidget\LinkWidget::getUriAsDisplayableString()
   * since I can't figure out another way to use a protected
   * method from that class.
   *
   * @param string $uri
   *   The URI to get the displayable string for.
   *
   * @return string
   *   The displayable string.
   *
   * @see Drupal\link\Plugin\Field\FieldWidget\LinkWidget::getUriAsDisplayableString()
   */
  protected static function getUriAsDisplayableString($uri): string {
    $scheme = parse_url($uri, PHP_URL_SCHEME);

    // By default, the displayable string is the URI.
    $displayable_string = $uri;

    // A different displayable string may be chosen in case of the 'internal:'
    // or 'entity:' built-in schemes.
    if ($scheme === 'internal') {
      $uri_reference = explode(':', $uri, 2)[1];

      // @todo '<front>' is valid input for BC reasons, may be removed by
      //   https://www.drupal.org/node/2421941
      $path = parse_url($uri, PHP_URL_PATH);
      if ($path === '/') {
        $uri_reference = '<front>' . substr($uri_reference, 1);
      }

      $displayable_string = $uri_reference;
    }
    elseif ($scheme === 'entity') {
      [$entity_type, $entity_id] = explode('/', substr($uri, 7), 2);
      // Show the 'entity:' URI as the entity autocomplete would.
      // @todo Support entity types other than 'node'. Will be fixed in
      //   https://www.drupal.org/node/2423093.
      if ($entity_type == 'node' && $entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($entity_id)) {
        $displayable_string = EntityAutocomplete::getEntityLabels([$entity]);
      }
    }
    elseif ($scheme === 'route') {
      $displayable_string = ltrim($displayable_string, 'route:');
    }

    return $displayable_string;
  }

}
