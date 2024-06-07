<?php

namespace Drupal\cevalidationsr\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'CevalidationsrFormBlock' block.
 *
 * @Block(
 * id = "Cevalidationsr_Form_block",
 * admin_label = @Translation("CeCredential Validation Scholar Record Form block"),
 * category = @Translation("Site custom")
 * )
 */
class CevalidationsrFormBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $form = \Drupal::formBuilder()->getForm('Drupal\cevalidationsr\Form\cevalidationsrForm');
    return $form;
  }

}
