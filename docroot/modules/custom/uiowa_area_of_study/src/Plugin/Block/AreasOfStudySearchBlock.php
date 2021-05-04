<?php

namespace Drupal\uiowa_area_of_study\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormState;

/**
 * Provides the Areas of Study Search block.
 *
 * @Block(
 *   id = "uiowa_area_of_study_search",
 *   admin_label = @Translation("Areas of Study Search"),
 *   category = @Translation("Site custom")
 * )
 */
class AreasOfStudySearchBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $form_state = new FormState();
    return \Drupal::formBuilder()->buildForm('Drupal\uiowa_area_of_study\Form\AreasOfStudySearchForm', $form_state);
  }

}
