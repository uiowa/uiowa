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
    $territories = [];
    $node_storage = $this->entityTypeManager->getStorage('node');
    $node = \Drupal::routeMatch()->getParameter('node');
    $nid = $node->id();
    $query = $node_storage->getQuery()
      ->condition('type', 'event')
      ->condition('status', 1)
      ->condition('field_event_series_link.uri', 'entity:node/' . $nid, '=');

    $nids = $query->execute();
    if (!empty($nids)) {
      $nodes = $node_storage->loadMultiple($nids);
      foreach ($nodes as $node) {
        // Get the field_event_series values and assign them to an array.
        if ($node->hasField('field_event_series_link') &&
          !$node->get('field_event_series_link')->isEmpty()) {
          $values = $node->get('field_event_series_link')->getValue();
          array_walk_recursive($values, function ($v) use (&$territories) {
            $territories[] = $v;
          });
        }
      }
      // Filter out territory duplicates.
      $territories = array_values(array_unique($territories));
    }

    return [
      '#type' => 'markup',
      '#markup' =>
      '<span>' . $this->t('Powered by <a href=":link">SiteNow</a>',
          [':link' => '$values']
      ) . '</span>',
      '#attached' => [
        'library' => [
          'admissions_core/counselors-map',
        ],
      ],
    ];
  }

}
