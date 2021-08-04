<?php

namespace Drupal\pharmacy_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an palliative care graduates map block.
 *
 * @Block(
 *   id = "pharmacy_core_palliative_care_map",
 *   admin_label = @Translation("Palliative Care Graduate Map"),
 *   category = @Translation("Site custom")
 * )
 */
class PalliativeGradMapBlock extends BlockBase implements ContainerFactoryPluginInterface {
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
    // Load persons tagged with the Palliative Care Graduate person type
    // and create array of unique territory values.
    $territories = [];
    $node_storage = $this->entityTypeManager->getStorage('node');
    $query = $node_storage->getQuery()
      ->condition('type', 'person')
      ->condition('field_person_types', 'palliative_grad');

    $nids = $query->execute();
    if (!empty($nids)) {
      $nodes = $node_storage->loadMultiple($nids);
      foreach ($nodes as $node) {
        // Get the field_person_territory values and assign them to an array.
        if ($node->hasField('field_person_territory') &&
          !$node->get('field_person_territory')->isEmpty()) {
          $values = $node->get('field_person_territory')->getValue();
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
      '#markup' => $this->t('<div id="pharmacy-palliative-grad-map">&nbsp;</div>'),
      '#cache' => [
        'tags' => ['node_type:person'],
      ],
      '#attached' => [
        'library' => [
          'uids_base/leaflet',
          'pharmacy_core/palliative-grad-map',
        ],
        'drupalSettings' => [
          'pharmacy_core' => [
            'territories' => $territories,
          ],
        ],
      ],
    ];
  }

}
