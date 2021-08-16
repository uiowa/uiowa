<?php

namespace Drupal\uiowa_area_of_study\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the Areas of Study Search block.
 *
 * @Block(
 *   id = "uiowa_area_of_study_search",
 *   admin_label = @Translation("Areas of Study Search"),
 *   category = @Translation("Site custom")
 * )
 */
class AreasOfStudySearchBlock extends BlockBase implements ContainerFactoryPluginInterface {
  protected $formBuilder;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, FormBuilderInterface $formBuilder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->formBuilder = $formBuilder;
  }

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
    $form_state = new FormState();
    return $this->formBuilder->buildForm('Drupal\uiowa_area_of_study\Form\AreasOfStudySearchForm', $form_state);
  }
}
