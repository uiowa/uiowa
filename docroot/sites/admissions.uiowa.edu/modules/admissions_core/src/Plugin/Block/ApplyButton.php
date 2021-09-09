<?php

namespace Drupal\admissions_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * An Apply button block.
 *
 * @Block(
 *   id = "applybutton_block",
 *   admin_label = @Translation("Apply Block"),
 *   category = @Translation("Admissions Core")
 * )
 */
class ApplyButton extends BlockBase {

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
    $markup = '<div class="layout-builder-block">
        <a class="bttn bttn--full bttn--primary bttn--caps" href="https://apply.admissions.uiowa.edu/admissions/login.page">
            Apply Now <span class="fa-arrow-right fas"></span>
        </a>
    </div>';

    return ['#markup' => $markup];
  }

}
