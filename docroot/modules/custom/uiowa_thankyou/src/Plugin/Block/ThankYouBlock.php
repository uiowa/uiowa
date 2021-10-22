<?php

namespace Drupal\uiowa_thankyou\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block which renders the thank you form.
 *
 * @Block(
 *   id = "thankyou_form",
 *   admin_label = @Translation("Thank You"),
 *   category = @Translation("Site custom")
 * )
 */
class ThankYouBlock extends BlockBase implements ContainerFactoryPluginInterface {
  /**
   * The form_builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * {@inheritdoc}
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
    $build['form'] = $this->formBuilder->getForm('\Drupal\uiowa_thankyou\Form\ThankYouForm');

    return $build;
  }

}
