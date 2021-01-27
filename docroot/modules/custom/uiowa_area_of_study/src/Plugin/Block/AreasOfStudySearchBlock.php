<?php

namespace Drupal\uiowa_areas_of_study\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the Areas of Study Search block.
 *
 * @Block(
 *   id = "uiowa_areas_of_study_search",
 *   admin_label = @Translation("Areas of Study Search"),
 *   category = @Translation("Site custom")
 * )
 */
class AreasOfStudySearchBlock extends BlockBase {
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
   * @param \Drupal\Core\Form\FormBuilderInterface $formBuilder
   *   The form_builder service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FormBuilderInterface $formBuilder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
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
      $container->get('form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    // Using a process described here:
    // https://drupal.stackexchange.com/a/274383/6066
    $form = [];
    $view_id = 'areas_of_study';
    $display_id = 'areas_of_study';
    $view = Views::getView($view_id);
    if ($view) {
      $view->setDisplay($display_id);
      $view->initHandlers();
      $form_state = (new FormState())
        ->setStorage([
          'view' => $view,
          'display' => &$view->display_handler->display,
          'rerender' => TRUE,
        ])
        ->setMethod('get')
        ->setAlwaysProcess()
        ->disableRedirect();
      $form_state->set('rerender', NULL);
      $form = $this->formBuilder
        ->buildForm('\Drupal\views\Form\ViewsExposedForm', $form_state);
    }

    return $form;
  }

}
