<?php

namespace Drupal\grad_student_profile\Entity;

use Drupal\uiowa_core\Entity\NodeBundleBase;
use Drupal\uiowa_core\Entity\RendersAsCardInterface;

/**
 * Provides an interface for grad.uiowa.edu student profile entries.
 */
class GradStudentProfile extends NodeBundleBase implements RendersAsCardInterface {

  /**
   * {@inheritdoc}
   */
  public function buildCard(array &$build) {
    parent::buildCard($build);

    // Process additional card mappings.
    $this->mapFieldsToCardBuild($build, [
      '#meta' => [
        'field_person_distinction',
      ],
      '#content' => 'body',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultCardStyles(): array {
    $default_classes = [
      ...parent::getDefaultCardStyles(),
      'card_media_position' => 'card--layout-left',
      'styles' => 'borderless',
      'headline_class' => 'headline--serif',
    ];

    return $default_classes;
  }

}
