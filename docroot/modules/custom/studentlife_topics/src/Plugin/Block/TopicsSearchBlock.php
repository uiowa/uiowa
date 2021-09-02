<?php

namespace Drupal\studentlife_topics\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the Topic page Search block.
 *
 * @Block(
 *   id = "topic_page_search",
 *   admin_label = @Translation("Topics Page Search"),
 *   category = @Translation("Site custom")
 * )
 */
class TopicsSearchBlock extends BlockBase implements ContainerFactoryPluginInterface {
  /**
   * The form_builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * AOS block constructor.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FormBuilderInterface $formBuilder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->formBuilder = $formBuilder;
  }

  /**
   * {@inheritdoc}
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
    $form_state = new FormState();
    return $this->formBuilder->buildForm('Drupal\studentlife_topics\Form\TopicsSearchForm', $form_state);
  }

}
