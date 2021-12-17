<?php

namespace Drupal\grad_admissions_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * An Apply button block.
 *
 * @Block(
 *   id = "applybutton_block",
 *   admin_label = @Translation("Apply Block"),
 *   category = @Translation("Site custom")
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
    $markup = '<div class="block-margin__bottom"> <div class="layout-builder-block">
        <a class="bttn bttn--full bttn--primary bttn--caps" href="https://apply.admissions.uiowa.edu/admissions/login.page">
            Apply Now <span class="fa-arrow-right fas"></span>
        </a>
    </div></div>';

    return ['#markup' => $markup];
  }

}
