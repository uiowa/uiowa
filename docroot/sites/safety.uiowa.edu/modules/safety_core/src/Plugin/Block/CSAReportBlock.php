<?php

namespace Drupal\safety_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'CSA Report' Block.
 *
 * @Block(
 *   id = "csa_report_block",
 *   admin_label = @Translation("CSA Incident Report Form"),
 *   category = @Translation("Site custom"),
 * )
 */
class CSAReportBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $form = \Drupal::formBuilder()->getForm('Drupal\safety_core\Form\CSAReportForm');
    return $form;
  }

}
