<?php

namespace Drupal\admissions_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides an admissions counselors map block.
 *
 * @Block(
 *   id = "admissions_core_admissions_counselors_map",
 *   admin_label = @Translation("Admissions Counselors Map"),
 *   category = @Translation("Admissions Core")
 * )
 */
class AdmissionsCounselorsMapBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $html = '<div id="admissions-counselors-map">&nbsp;</div>';
    // Load persons tagged with the Counselor person type
    // and create array of unique territory values.
    $territories = [];
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'person')
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
      $territories = array_unique($territories);
    }

    return [
      '#type' => 'markup',
      '#markup' => $this->t($html),
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
