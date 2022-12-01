<?php

namespace Drupal\admissions_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an admissions counselors map block.
 *
 * @Block(
 *   id = "admissions_core_admissions_counselors_map",
 *   admin_label = @Translation("Counselors Map"),
 *   category = @Translation("Site custom")
 * )
 */
class AdmissionsCounselorsMapBlock extends BlockBase implements ContainerFactoryPluginInterface {
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
    // Load persons tagged with the Counselor person type
    // and create array of unique territory values.
    $territories = [];
    $node_storage = $this->entityTypeManager->getStorage('node');
    $query = $node_storage->getQuery()
      ->condition('type', 'person')
      ->condition('status', 1)
      ->condition('field_person_types', 'counselor');

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
      '#markup' => $this->t('<div id="admissions-counselors-map">&nbsp;</div>'),
      '#cache' => [
        'tags' => ['node_type:person'],
      ],
      '#attached' => [
        'library' => [
          'uids_base/leaflet',
          'admissions_core/counselors-map',
        ],
        'drupalSettings' => [
          'admissions_core' => [
            'territories' => $territories,
          ],
        ],
      ],
    ];
  }

}
