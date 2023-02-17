<?php

namespace Drupal\admissions_core\Entity;

use Drupal\uiowa_core\Entity\NodeBundleBase;
use Drupal\uiowa_core\Entity\TeaserCardInterface;

/**
 * Provides an interface for student profile page entries.
 */
class StudentProfile extends NodeBundleBase implements TeaserCardInterface {

  /**
   * {@inheritdoc}
   */
  public function buildCard(array &$build) {
    parent::buildCard($build);

    // Process additional card mappings.
    $this->mapFieldsToCardBuild($build, [
      '#media' => 'field_student_profile_image',
      '#subtitle' => 'field_student_profile_major',
      '#meta' => [
        'field_student_profile_state',
        'field_student_profile_country',
      ],
    ]);

  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultCardStyles(): array {
    $default_classes = [
      ...parent::getDefaultCardStyles(),
      'card_media_position' => '',
      'media_border' => 'media--border',
      'media_format' => 'media--circle',
      'media_size' => 'media--medium',
      'headline_class' => 'headline--uppercase headline',
      'styles' => '',
    ];

    return $default_classes;
  }

}
