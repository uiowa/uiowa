<?php

namespace Drupal\pharmacy_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides an palliative care graduates map block.
 *
 * @Block(
 *   id = "pharmacy_core_palliative_care_map",
 *   admin_label = @Translation("Palliative Care Graduate Map"),
 *   category = @Translation("Site custom")
 * )
 */
class PalliativeGradMapBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $html = '<div id="pharmacy-palliative-grad-map">&nbsp;</div>';
    // Load persons tagged with the Palliative Care Graduate person type
    // and create array of unique territory values.
    $territories = [];
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    $query = \Drupal::entityQuery('node')
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
      '#markup' => $this->t($html),
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
