<?php

namespace Drupal\uiowa_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Fragmentity block' Block.
 *
 * @Block(
 *   id = "uiowa_core_fragmentity_block",
 *   admin_label = @Translation("Fragmentity block"),
 *   category = @Translation("Site custom")
 * )
 */
class FragmentityBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $config;

  /**
   * The entity_type.manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The current_route_match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected RouteMatchInterface $routeMatch;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, RouteMatchInterface $route_match) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->config = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): FragmentityBlock {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('current_route_match'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    // Inspired by https://drupal.stackexchange.com/a/239317.
    $block_id = $form['id']['#value'] ?? $form['id']['#default_value'];
    $this->configuration['block_id'] = $block_id;
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    $config = $this->getConfiguration();
    $fid = $config['fid'] ?? NULL;
    $form['fragmentity_reference'] = [
      '#type' => 'entity_autocomplete',
      '#title' => 'Fragmentity reference',
      '#description' => $this->t('Enter the name of an existing Fragmentity.'),
      '#target_type' => 'fragment',
      '#default_value' => $fid != NULL ? $this->entityTypeManager->getStorage('fragment')->load($fid) : NULL,
//      '#selection_settings' => [
//        'target_bundles' => [
//          $region_item_machine_name,
//        ],
//      ],
    ];


    $form['example'] = [
      '#type' => 'markup',
      '#markup' => $this->t('<div class="form-item__description">TEST TEST</div>'),
    ];

    return $form;

  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $config = $this->getConfiguration();
    $block_id = $config['block_id'];
    $node = $this->routeMatch->getParameter('node');
    $fragment = NULL;

//    // Check if there is a fragment override for this block.
//    if (!is_null($node) && $node instanceof NodeInterface && $node->bundle() === 'page') {
//      // Get the fragment id from the appropriate field on the node.
//      $field_name = "field_{$block_id}_override";
//      $override_id = $node
//        ?->{$field_name}
//      ?->target_id;
//
//      // If we have an ID, attempt to load the fragment.
//      if (!is_null($override_id)) {
//        $fragment = $this->entityTypeManager
//          ->getStorage('fragment')
//          ->load($override_id);
//      }
//    }
//
//    // If there is no override, check the global setting.
//    if (is_null($fragment)) {
//      $uiowa_core_settings = $this->config->get('uiowa_core.settings');
//      $fid = $uiowa_core_settings->get('uiowa_core.region_content.' . $block_id);
//
//      // If there is no global setting, then we're done.
//      if (is_null($fid)) {
//        return $build;
//      }
//
//      // Attempt to load the fragment from the global setting.
//      $fragment = $this->entityTypeManager
//        ->getStorage('fragment')
//        ->load($fid);
//    }
//
//    // If we have a fragment...
//    if (!is_null($fragment)) {
//      // Get the rendered display for the fragment.
//      $fragment = $this->entityTypeManager
//        ->getViewBuilder('fragment')
//        ->view($fragment);
//
//      // Create the render array for the block.
//      $build['fragment'] = $fragment;
//      $build['#contextual_links'] = [
//        'region_settings' => [
//          'route_parameters' => ['regions_settings' => 'region_settings'],
//        ],
//      ];
//    }
    $build['example'] = [
      '#type' => 'markup',
      '#markup' => $this->t('<div class="form-item__description">TEST TEST</div>'),
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    if ($node = $this->routeMatch->getParameter('node')) {
      return Cache::mergeTags(parent::getCacheTags(), ['node:' . $node->id()]);
    }
    else {
      return parent::getCacheTags();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    // This module often depends on the route to know which node to load, so
    // route should be part of the cache context.
    return Cache::mergeContexts(parent::getCacheContexts(), ['route']);
  }

}
