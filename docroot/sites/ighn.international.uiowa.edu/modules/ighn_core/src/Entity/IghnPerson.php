<?php

namespace Drupal\ighn_core\Entity;

use Drupal\uiowa_core\Entity\NodeBundleBase;
use Drupal\uiowa_core\Entity\RendersAsCardInterface;

/**
 * Provides an interface for IGHN person entries.
 */
class IghnPerson extends NodeBundleBase implements RendersAsCardInterface {

  /**
   * {@inheritdoc}
   */
  public function buildCard(array &$build) {
    parent::buildCard($build);

    // Add the person library.
    $build['#attached']['library'][] = 'uids_base/person';

    // Process additional card mappings.
    $this->mapFieldsToCardBuild($build, [
      '#media' => 'field_ighn_person_image',
      '#subtitle' => 'field_ighn_person_credentials',
//      '#url' => 'field_ighn_person_cv_link',
      '#title_heading_size' => 'h3',
      '#content' => [
        'field_ighn_person_position_title',
        'field_ighn_primary_college',
        'field_ighn_person_department',
        'field_ighn_person_focus_areas',
        'field_ighn_person_language',
        'field_ighn_person_global_regions',
        'field_ighn_person_biography',
      ],
    ]);

  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultCardStyles(): array {
    $default_classes = [
      ...parent::getDefaultCardStyles(),
      'media_format' => 'media',
      'media_size' => 'media',
      'styles' => '',
    ];

    return $default_classes;
  }

}
