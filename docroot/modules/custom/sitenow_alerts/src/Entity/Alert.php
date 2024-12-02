<?php

namespace Drupal\sitenow_alerts\Entity;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\uiowa_core\Entity\NodeBundleBase;
use Drupal\uiowa_core\Entity\RendersAsCardInterface;

/**
 * Provides an interface for alert entries.
 */
class Alert extends NodeBundleBase implements ClosableInterface, RendersAsCardInterface {

  /**
   * {@inheritdoc}
   */
  public function buildCard(array &$build) {
    parent::buildCard($build);

    // Process additional card mappings.
    $this->mapFieldsToCardBuild($build, [
      '#content' => 'body',
      '#meta' => [
        'field_alert_date',
        'field_alert_category',
      ],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultCardStyles(): array {
    return array_merge(
      parent::getDefaultCardStyles(),
      [
        'styles' => '',
        'card_headline_style' => 'headline--serif',
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function isClosed(): ?bool {
    if (!$this->hasField('field_alert_date')) {
      return NULL;
    }
    $start_time = $this->field_alert_date?->value;
    $end_time = $this->field_alert_date?->end_value;
    $current_time = (new DrupalDateTime())->getTimestamp();

    // If the end time equals start time, it means
    // the alert is "ongoing," and so is not closed.
    // And if the alert end time has not passed, then
    // it is either current and upcoming,
    // and so should be considered open.
    return ($end_time !== $start_time && $current_time > $end_time);
  }

}
