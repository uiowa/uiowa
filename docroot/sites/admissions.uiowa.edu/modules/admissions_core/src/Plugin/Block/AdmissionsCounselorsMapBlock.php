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
    $html = '<div id="admissions-counselors-map"></div>';
    return [
      '#type' => 'markup',
      '#markup' => $this->t($html),
      '#attached' => [
        'library' => [
          'uids_base/leaflet',
          'admissions_core/counselors-map',
        ],
      ],
    ];
  }

}
