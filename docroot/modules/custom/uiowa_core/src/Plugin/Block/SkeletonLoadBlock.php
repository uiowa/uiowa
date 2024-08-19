<?php

namespace Drupal\uiowa_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an 'Skeleton Load' block.
 *
 * @Block(
 *   id = "skeleton_load_block",
 *   admin_label = @Translation("Skeleton Load"),
 *   category = @Translation("Site custom")
 * )
 */
class SkeletonLoadBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['label_display' => FALSE];
  }

  /**
   * {@inheritdoc}
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
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {

    $build['skeleton_load'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => ['skeleton-load'],
      ],
    ];
    $build['#attached']['library'][] = 'uiowa_core/skeletonLoad';
    $build['skeleton_load']['heading'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => [
          'headline',
          'h6',
        ],
      ],
    ];

    $build['skeleton_load']['heading']['content'] = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => 'Giacomo DiBella',
      '#attributes' => [
        'class' => ['headline__heading'],
      ],
    ];

    $build['skeleton_load']['closet'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => ['skeleton-load__closet'],
      ],
    ];

    $build['skeleton_load']['closet']['skeleton'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => ['skeleton-load__skeleton'],
      ],
    ];

    $build['skeleton_load']['closet']['skeleton']['skull'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => [
          'skeleton-load__skull',
          'sheen'
        ],
      ],
    ];

    $build['skeleton_load']['closet']['skeleton']['bones'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => ['skeleton-load__bones'],
      ],
    ];

    $build['skeleton_load']['closet']['skeleton']['bones']['bone_1'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => [
          'skeleton-load__bone',
          'sheen'
        ],
      ],
    ];

    $build['skeleton_load']['closet']['skeleton']['bones']['bone_2'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => [
          'skeleton-load__bone',
          'sheen'
        ],
      ],
    ];

    return $build;
  }
}
