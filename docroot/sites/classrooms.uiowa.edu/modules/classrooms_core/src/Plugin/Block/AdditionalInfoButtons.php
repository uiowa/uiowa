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
    $markup = '<div class="layout-builder-block">
      <a class="bttn bttn--secondary" role="presentation" href="http://classroomscheduling.registrar.uiowa.edu/AdAstra7Prod/Portal/GuestPortal.aspx">
          Check Availability <span class="fa-arrow-right fas"></span>
      </a>
      <a class="bttn bttn--secondary" role="presentation" href="https://workflow.uiowa.edu/entry/new/667/">
        Request this Room <span class="fa-arrow-right fas"></span>
      </a>
      <a class="bttn bttn--primary" role="presentation" href="https://classrooms.prod.drupal.uiowa.edu/classroom-assistance">
        Report an Issue <span class="fa-arrow-right fas"></span>
      </a>
    </div>';

    return ['#markup' => $markup];
  }

}
