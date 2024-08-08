<?php

namespace Drupal\its_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an 'Alert Type Legend' block.
 *
 * @Block(
 *   id = "alert_type_legend_block",
 *   admin_label = @Translation("Alert Type Legend"),
 *   category = @Translation("Site custom")
 * )
 */
class AlertTypeLegend extends BlockBase implements ContainerFactoryPluginInterface, FormInterface {

  /**
   * Constructs a new AcademicCalendarBlock instance.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'its_alert_type_legend';
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Add any validation if needed.
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Handle form submission if needed.
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    return parent::blockForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function build() {

    $build = [
      'wrapper' => [
        '#type' => 'markup',
        '#markup' =>
        '<div class="card__meta" >' .
        '<div class="field__item"><span class="block-margin__top badge badge--orange"><i class="svg-inline--fa fas fa-triangle-exclamation"></i>Outage</span></div>' .
        '<div class="field__item"><span class="block-margin__top badge badge--green">Planned Maintenance</span></div>' .
        '<div class="field__item"><span class="block-margin__top badge badge--blue"><i class="svg-inline--fa fas fa-arrow-trend-down"></i></svg>Service Degradation</span></div>' .
        '<div class="field__item"><span class="block-margin__top badge badge--cool-gray">Service Announcement</span></div>' .
        '</div>',
      ],
    ];

    return $build;
  }

  /**
   * Builds the form elements for the block.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    return $form;
  }

}
