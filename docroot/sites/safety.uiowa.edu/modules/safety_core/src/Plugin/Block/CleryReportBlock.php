<?php

namespace Drupal\safety_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'Clery Report' Block.
 *
 * @Block(
 *   id = "clery_report_block",
 *   admin_label = @Translation("Clery incident report form"),
 *   category = @Translation("Site custom"),
 * )
 */
class CleryReportBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $form = \Drupal::formBuilder()->getForm('Drupal\safety_core\Form\CleryReportForm');
    return $form;
  }

}
