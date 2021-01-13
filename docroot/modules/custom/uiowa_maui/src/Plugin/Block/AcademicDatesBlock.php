<?php

namespace Drupal\uiowa_maui\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\layout_builder_custom\HeadlineHelper;
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
   * Override the construction method.
   *
   * @param array $configuration
   *   The block configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\uiowa_maui\MauiApi $maui
   *   The MAUI API class.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MauiApi $maui) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->maui = $maui;
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
      $container->get('uiowa_maui.api')
    );
  }

  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    $config = $this->getConfiguration();

    $form['headline'] = HeadlineHelper::getElement([
      'headline' => $config['heading']['headline'] ?? NULL,
      'hide_headline' => $config['heading']['hide_headline'] ?? 0,
      'heading_size' => $config['heading']['heading_size'] ?? 'h2',
      'headline_style' => $config['heading']['headline_style'] ?? 'default',
      'child_heading_size' => $config['heading']['child_heading_size'] ?? 'h2',
    ]);

    $form['category'] = [
      '#type' => 'select',
      '#title' => $this->t('Category'),
      '#description' => $this->t('Select a category to filter dates on.'),
      '#default_value' => $config['category'] ?? NULL,
      '#empty_value' => NULL,
      '#empty_option' => $this->t('- All -'),
      '#options' => $this->maui->getDateCategories(),
    ];

    return $form;
  }

  public function blockSubmit($form, FormStateInterface $form_state) {
    // Alter the headline field settings for configuration.
    foreach ($form_state->getValues()['headline']['container'] as $name => $value) {
      $this->configuration['heading'][$name] = $value;
    }

    $this->configuration['category'] = $form_state->getValue('category');
    parent::blockSubmit($form, $form_state);
  }

  /**
   * Build the block.
   */
  public function build() {
    $config = $this->getConfiguration();

    // @todo Write a headline theme function to render it here.
    return \Drupal::formBuilder()->getForm(
      '\Drupal\uiowa_maui\Form\DatesBySessionForm',
      $config['heading'],
      $config['category'],
    );
  }

}
