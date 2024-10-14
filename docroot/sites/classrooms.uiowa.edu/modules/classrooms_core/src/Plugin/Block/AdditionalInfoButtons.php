<?php

namespace Drupal\classrooms_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * A Request Info button block.
 *
 * @Block(
 *   id = "requestinfobutton_block",
 *   admin_label = @Translation("Request Info Block"),
 *   category = @Translation("Site custom")
 * )
 */
class AdditionalInfoButtons extends BlockBase {

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
    $build = [
      '#theme' => 'requestinfobutton_block',
      '#check_availability_link' => 'https://www.aaiscloud.com/uiowa',
      '#request_link' => 'https://workflow.uiowa.edu/entry/new/667/',
      '#report_issue_link' => '/classroom-assistance',
    ];

    return $build;
  }

}
