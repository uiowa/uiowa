<?php

namespace Drupal\admissions_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * An Area of Study Buttons block.
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
    $markup = '<div class="element--bold-intro text-align-center">
            New Headline
    </div>';

    return ['#markup' => $markup];
  }
}
