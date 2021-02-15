<?php

namespace Drupal\admissions_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * An Area of Study Requirements Heading block.
 *
 * @Block(
 *   id = "aosrequirements_block",
 *   admin_label = @Translation("Area of Study Requirements Heading Block"),
 *   category = @Translation("Area of Study")
 * )
 */
class AOSRequirements extends BlockBase {

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
      '#markup' => $this->t('Admission Process'),
      '#prefix' => '<div class="element--bold-intro text-align-center">',
      '#suffix' => '</div>',
    ];
  }

}
