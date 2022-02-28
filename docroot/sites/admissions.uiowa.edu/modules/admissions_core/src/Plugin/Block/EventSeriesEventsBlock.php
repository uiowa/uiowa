<?php

namespace Drupal\admissions_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\node\NodeInterface;
use Drupal\path_alias\AliasManagerInterface;
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
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The routeMatch.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected RouteMatchInterface $routeMatch;

  /**
   * The path_alias.manager service.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected AliasManagerInterface $aliasManager;

  /**
   * The date.formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected DateFormatterInterface $dateFormat;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entityTypeManager, RouteMatchInterface $routeMatch, AliasManagerInterface $aliasManager, DateFormatterInterface $dateFormatter) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
    $this->routeMatch = $routeMatch;
    $this->aliasManager = $aliasManager;
    $this->dateFormat = $dateFormatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('current_route_match'),
      $container->get('path_alias.manager'),
      $container->get('date.formatter'),

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
    $node = $this->routeMatch->getParameter('node');
    if ($node instanceof NodeInterface) {
      $nid = $node->id();
      $query = $node_storage->getQuery()
        ->condition('type', 'event')
        ->condition('status', 1)
        ->condition('field_event_series_link.uri', 'entity:node/' . $nid, '=')
        ->sort('field_event_when.value', 'ASC');

      $nids = $query->execute();
      if (!empty($nids)) {
        $nodes = $node_storage->loadMultiple($nids);
        foreach ($nodes as $node) {
          // Get the field_event_series values and assign them to an array.
          if ($node->hasField('field_event_when') &&
            !$node->get('field_event_when')->isEmpty()) {
            $nid = $node->id();
            $node_when = $node->get('field_event_when')->getValue();
            $date = $this->dateFormat->format($node_when[0]['value'], 'custom', 'D, M j');
            $markup = [
              '#markup' => '<span class="fa-li"><span class="fa-angle-right text--gold fas"></span></span>' . $date,
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
      $block = [
        '#prefix' => $this->t('<div class="block-margin__top field__label"><span class="fa-calendar far"></span> &nbsp; Available dates:</div>'),
        '#theme' => 'item_list',
        '#list_type' => 'ul',
        '#attributes' => ['class' => 'element--list-none fa-ul'],
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
    else {
      $block = [
        '#markup' => '<p>Placeholder for Event Series block</p>',
      ];
    }
    return $block;
  }

}
