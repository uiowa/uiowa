<?php

namespace Drupal\sitenow_signage\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a Date Time block.
 *
 * @Block(
 *   id = "datetime_block",
 *   admin_label = @Translation("Date Time Block"),
 *   category = @Translation("Site custom")
 * )
 */
class DateTime extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['label_display' => FALSE];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#type' => 'inline_template',
      '#attached' => [
        'library' => [
          'sitenow_signage/datetime',
        ],
      ],
      '#template' => '
        <div class="date-time">
          <span id="datespan">&nbsp;</span><span id="timespan">&nbsp;</span>
        </div>
      ',
    ];
  }

}
