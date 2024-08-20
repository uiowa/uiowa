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
        'aria-busy' => ['true']
      ],
    ];
    $build['#attached']['library'][] = 'uiowa_core/skeletonLoad';
    $build['skeleton_load']['heading'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#attributes' => [
        'class' => [
          'skeleton-load__bone',
          'sheen' ,
          'headline',
          'headline--serif',
          'block-margin__bottom--extra',
          'block-padding__top',
          'marrowed'
        ],
        'aria-hidden' => ['true']
      ],
    ];

    $build['skeleton_load']['heading']['content'] = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => 'Giacomo Ultimocuore',
      '#attributes' => [
        'class' => ['headline__heading'],
        'aria-hidden' => ['true']
      ],
    ];

    $build['skeleton_load']['closet'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => ['skeleton-load__closet'],
        'aria-hidden' => ['true']
      ],
    ];

    $skeleton = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => ['skeleton-load__skeleton'],
      ],
    ];
    $skeleton['skull'] =  [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => [
          'skeleton-load__skull',
          'sheen'
        ],
      ],
    ];
    $skeleton['bones'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => ['skeleton-load__bones'],
      ],
    ];
    $skeleton['bones']['humerus'] = [
      '#type' => 'html_tag',
      '#tag' => 'h3',
      '#value' => 'Sognatore Di Pace',
      '#attributes' => [
        'class' => [
          'skeleton-load__bone',
          'sheen',
          'marrowed'
        ],
      ],
    ];
    $skeleton['bones']['radius'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => [
          'skeleton-load__bone',
          'sheen',
          'body'
        ],
      ],
    ];
    $skeleton['bones']['phalanx'] = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => 'DiBella',
      '#attributes' => [
        'class' => [
          'skeleton-load__bone',
          'sheen',
          'phalanx'
        ],
      ],
    ];
    $build['skeleton_load']['closet'][] = $skeleton;
    $build['skeleton_load']['closet'][] = $skeleton;
    $build['skeleton_load']['closet'][] = $skeleton;

    return $build;
  }
}
