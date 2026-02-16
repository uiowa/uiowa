<?php

namespace Drupal\facilities_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Url;

/**
 * A Utility Alerts block.
 *
 * @Block(
 *   id = "utility_alerts_block",
 *   admin_label = @Translation("Utility Alerts Block"),
 *   category = @Translation("Site custom"),
 * )
 */
class UtilityAlertsBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#markup' => '<div class="utility-alerts-container"><p>Loading utility alerts...</p></div>',
      '#attached' => [
        'library' => [
          'facilities_core/utility_alerts',
        ],
        'drupalSettings' => [
          'facilities_core' => [
            'utilityAlertsUrl' => Url::fromRoute('facilities_core.utility_alerts')->toString(),
          ],
        ],
      ],
    ];
  }

}
