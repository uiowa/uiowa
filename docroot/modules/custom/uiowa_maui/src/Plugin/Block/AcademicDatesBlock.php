<?php

namespace Drupal\uiowa_maui\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
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

    $this->configuration['session'] = ($session === '') ? NULL : $session;
    $this->configuration['category'] = ($category === '') ? NULL : $category;
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
      $child_heading_size
    );

    return $build;
  }

}
