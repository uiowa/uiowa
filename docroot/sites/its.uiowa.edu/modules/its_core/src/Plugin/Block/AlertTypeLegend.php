<?php

namespace Drupal\its_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an 'Alert Type Legend' block.
 *
 * @Block(
 *   id = "alert_type_legend_block",
 *   admin_label = @Translation("Alert type legend"),
 *   category = @Translation("Site custom")
 * )
 */
class AlertTypeLegend extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The entity_type.manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['label_display' => FALSE];
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $map = its_core_alert_type_tag_map();
    $tids = array_column($map, 'tid');
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadMultiple($tids);
    $badgeMarkup = '<p>';

    foreach ($terms as $term) {
      foreach ($map as $data) {
        if ($term->id() == $data['tid']) {
          $name = $term->name->value;
          $description = trim(preg_replace('/\s\s+/', '', strip_tags($term->description->value)));
          $color = $data['color'];

          $badgeMarkup .= '<span class="block-margin__top badge badge--' . $color . '" title="' . $description . '">' . $name . '</span> ';
        }
      }
    }

    $badgeMarkup .= '</p>';

    $build['alert_type_legend'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => [''],
      ],
    ];
    $build['alert_type_legend']['heading'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => [
          'headline',
          'h6',
        ],
      ],
    ];

    $build['alert_type_legend']['heading']['content'] = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => 'Legend',
      '#attributes' => [
        'class' => ['headline__heading'],
      ],
    ];
    $build['alert_type_legend']['badges'] = [
      'wrapper' => [
        '#type' => 'markup',
        '#markup' => $badgeMarkup,
      ],
    ];

    return $build;
  }

}
