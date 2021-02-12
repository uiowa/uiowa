<?php

namespace Drupal\admissions_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * An Area of Study Buttons block.
 *
 * @Block(
 *   id = "aosbuttons_block",
 *   admin_label = @Translation("Area of Study Buttons Block"),
 *   category = @Translation("Area of Study")
 * )
 */
class AOSButtons extends BlockBase {

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
    $markup = '<div class="layout-builder-block block-margin__bottom">
        <a class="bttn bttn--full bttn--primary bttn--caps" href="https://apply.admissions.uiowa.edu/admissions/login.page">
            Apply Now <span class="fa-arrow-right fas"></span>
        </a>
    </div>
    <div class="layout-builder-block block-margin__bottom block-margin__top--extra">
      <a class="bttn bttn--full bttn--secondary bttn--caps" href="https://www.maui.uiowa.edu/maui/pub/admissions/webinquiry/undergraduate.page">
          Request Info <span class="fa-arrow-right fas"></span>
      </a>
    </div>';

    return ['#markup' => $markup];
  }

}
