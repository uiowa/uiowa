<?php

namespace Drupal\admissions_core\Entity;

use Drupal\uiowa_core\Entity\NodeBundleBase;
use Drupal\uiowa_core\Entity\RendersAsCardInterface;

/**
 * Provides an interface for student profile page entries.
 */
class StudentProfile extends NodeBundleBase implements RendersAsCardInterface {

  /**
   * {@inheritdoc}
   */
  public function buildCard(array &$build) {
    parent::buildCard($build);

    $this->mapFieldsToCardBuild($build, [
      '#media' => 'field_student_profile_image',
      '#subtitle' => 'field_student_profile_major',
      '#meta' => [
        'field_person_hometown',
      ],
    ]);

  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultCardStyles(): array {
    $default_classes = [
      ...parent::getDefaultCardStyles(),
      'card_media_position' => 'card--stacked',
      'media_border' => 'media--border',
      'media_format' => 'media--circle',
      'media_size' => 'media--medium',
      'card_headline_style' => 'headline--uppercase',
      'styles' => 'bg--white',
    ];

    return $default_classes;
  }

}
