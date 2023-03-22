<?php

namespace Drupal\classrooms_core\Entity;

use Drupal\uiowa_core\Entity\NodeBundleBase;
use Drupal\uiowa_core\Entity\RendersAsCardInterface;

/**
 * Provides an interface for area of study page entries.
 */
class ClassroomsRoom extends NodeBundleBase implements RendersAsCardInterface {

  /**
   * {@inheritdoc}
   */
  public function buildCard(array &$build) {
    parent::buildCard($build);

    // Default meta fields.
    $meta_fields = [
      'field_room_name',
      'field_room_max_occupancy',
    ];

    // View specific fields.
    if ($this->view) {
      // Units taxonomy.
      if ($this->view->id() === 'taxonomy_term') {
        $meta_fields[] = 'field_room_type';
        $meta_fields[] = 'field_room_responsible_unit';
      }
      // Find a classroom.
      if ($this->view->id() === 'room_list') {
        if ($this->view->current_display === 'block_rooms') {
          $meta_fields[] = 'field_room_type';
        }
      }
    }

    // Process additional card mappings.
    $this->mapFieldsToCardBuild($build, [
      '#meta' => $meta_fields,
    ]);

    $building_id = $this->get('field_room_building_id')->target_id;

    // Get the building ID and room ID fields.
    $room_id = $this->get('field_room_room_id')->value;

    // Combine the fields to create the title.
    $title_combined = strtoupper($building_id) . ' ' . $room_id;
    $title = ['#markup' => $title_combined];
    $build['#title'] = $title;
    $build['#link_indicator'] = TRUE;

  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultCardStyles(): array {
    return [
      ...parent::getDefaultCardStyles(),
      'card_media_position' => 'card--stacked',
      'media_size' => 'media--large',
      'headline_class' => 'headline--serif headline--underline h4',
      'styles' => 'card--button-align-bottom',
      'border' => '',
    ];
  }

}
