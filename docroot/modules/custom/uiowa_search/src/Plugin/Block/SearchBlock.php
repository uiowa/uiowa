<?php

namespace Drupal\uiowa_search\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides global search block for Google.
 *
 * @Block(
 *   id = "uiowa_search_form",
 *   admin_label = @Translation("UIowa Search"),
 *   category = @Translation("Restricted")
 * )
 */
class SearchBlock extends BlockBase implements ContainerFactoryPluginInterface {
  /**
   * The form_builder service.
   */
  protected FormBuilderInterface $formBuilder;

  /**
   * The config_factory service.
   */
  private ConfigFactory $configFactory;

  /**
   * Search block constructor.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FormBuilderInterface $formBuilder, ConfigFactory $configFactory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->formBuilder = $formBuilder;
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('form_builder'),
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    if ($this->configFactory->get('uiowa_search.settings')->get('uiowa_search.display_search')) {
      $build['form'] = $this->formBuilder->getForm('Drupal\uiowa_search\Form\SearchForm');
    }
    return $build;
  }

}
