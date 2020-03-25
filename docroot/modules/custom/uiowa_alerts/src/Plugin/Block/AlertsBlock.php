<?php

namespace Drupal\uiowa_alerts\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a block of alerts.
 *
 * @Block(
 *   id = "uiowa_alerts_block",
 *   admin_label = @Translation("Alerts block"),
 * )
 */
class AlertsBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $no_alerts_message = trim(variable_get('uiowa_alerts_no_alerts_message', ''));
    $filtered_message = filter_xss($no_alerts_message, $allowed_tags = array('a', 'p', 'div', 'em', 'strong'));
    $source = variable_get('uiowa_alerts_source', 'json_production');
    switch ($source) {
      case 'json_production':
        $source_url = 'https://emergency.uiowa.edu/api/active.json';
        break;

      case 'json_test':
        $source_url = 'https://emergency.stage.drupal.uiowa.edu/api/active.json';
        break;
    }
    return [
      '#markup' => '<div class="uiowa-alerts-wrapper"></div>',
      '#attached' => [
        'library' => [
          'uiowa_alerts/uiowa-alerts',
        ],
        'drupalSettings' => [
          'uiowaAlerts' => [
            'alertSource' => $source_url,
            'noAlertsMessage' => $filtered_message,
          ],
        ],
      ]
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }
}