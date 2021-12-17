<?php

namespace Drupal\grad_admissions_core\Plugin\Block;

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
class RequestInfoButton extends BlockBase {

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
      <a class="bttn bttn--full bttn--secondary bttn--caps" href="https://www.maui.uiowa.edu/maui/pub/admissions/webinquiry/undergraduate.page">
          Request Info <span class="fa-arrow-right fas"></span>
      </a>
    </div></div>';

    return ['#markup' => $markup];
  }

}
