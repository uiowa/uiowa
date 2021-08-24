<?php

namespace Drupal\admissions_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a list of events related to the event series.
 *
 * @Block(
 *   id = "admissions_core_event_series_events",
 *   admin_label = @Translation("Event Series Events Block"),
 *   category = @Translation("Site custom")
 * )
 */
class EventSeriesEventsBlock extends BlockBase implements ContainerFactoryPluginInterface {
  /**
   * The entity_type.manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

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
    // Load events that are linked to the event series content type
    // and create array of unique values.
    $dates = [];
    $node_storage = $this->entityTypeManager->getStorage('node');
    $node = \Drupal::routeMatch()->getParameter('node');
    $nid = $node->id();
    $query = $node_storage->getQuery()
      ->condition('type', 'event')
      ->condition('status', 1)
      ->condition('field_event_series_link.uri', 'entity:node/' . $nid, '=')
      ->sort('field_event_when.value' , 'ASC');

    $nids = $query->execute();
    if (!empty($nids)) {
      $nodes = $node_storage->loadMultiple($nids);
      foreach ($nodes as $node) {
        // Get the field_event_series values and assign them to an array.
        if ($node->hasField('field_event_when') &&
          !$node->get('field_event_when')->isEmpty()) {
          $nid = $node->id();
          $alias = \Drupal::service('path_alias.manager')->getAliasByPath('/node/' . $nid);
          $node_when = $node->get('field_event_when')->getValue();
          $date = \Drupal::service('date.formatter')->format($node_when[0]['value'], 'medium');
          $markup = [
            '#markup' => '<a href="' . $alias . '">' . $date . '</a>',
          ];
          $dates[$nid] = $markup;
        }
      }
    }

    if (empty($dates)) {
      $markup = [
        '#markup' => '<p>There are currently no events to display.</p>',
      ];
      $dates[] = $markup;
    }

    return [
      '#theme' => 'item_list',
      '#list_type' => 'ul',
      '#cache' => [
        'tags' => ['node_type:event'],
      ],
      '#items' => $dates,
      '#attached' => [
        'library' => [
          'admissions_core/event-series',
        ],
      ],
    ];
  }

}
