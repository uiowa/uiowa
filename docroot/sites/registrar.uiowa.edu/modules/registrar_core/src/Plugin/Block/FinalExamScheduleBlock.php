<?php

namespace Drupal\registrar_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a Final Exam Schedule block.
 *
 * @Block(
 *   id = "final_exam_schedule_block",
 *   admin_label = @Translation("Final Exam Schedule"),
 *   category = @Translation("Site custom")
 * )
 */
class FinalExamScheduleBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $markup = '<div>Hello World!</div>';
    return ['#markup' => $markup];
  }

}
