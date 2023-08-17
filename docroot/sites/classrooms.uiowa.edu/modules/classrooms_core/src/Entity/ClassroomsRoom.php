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

    // We need this processing to only happen once. Because we store the
    // modified value of the 'field_room_features' field back on the node, the
    // second time buildCard() is called, the code to match terms by them
    // starting with 'Seats' doesn't work because we've already stripped that
    // out. To prevent it from being executed twice, we set a variable on the
    // node and check it first to see if we've already been here. The upside is
    // that we can just include the field as normal below, and it will react to
    // being hidden correctly.
    if (is_null($this->seats_processed)) {
      // Remove all terms except ones with "Seats" in field_room_features.
      $taxonomy = $this->get('field_room_features')->referencedEntities();
      $seats = [];

      foreach ($taxonomy as $term) {
        if (str_starts_with($term->getName(), 'Seats')) {
          $name = str_replace('Seats - ', '', $term->getName());
          $term->setName($name);
          $seats[] = $term;
        }
      }

      // Update the field value with the new list of seats.
      $this->set('field_room_features', $seats);

      $this->seats_processed = TRUE;
    }

    $build['field_room_features'] = $this->field_room_features
      ?->view('teaser');

    $this->mapFieldsToCardBuild($build, [
      '#meta' => [
        'building_link',
        'field_room_name',
        'field_room_max_occupancy',
        'field_room_type',
        'field_room_responsible_unit',
        'field_room_features',
      ],
    ]);

    $building_id = $this->get('field_room_building_id')->target_id;

    // Get the building ID and room ID fields.
    $room_id = $this->get('field_room_room_id')->value;

    // Combine the fields to create the title.
    $build['#title'] = ['#markup' => strtoupper($building_id) . ' ' . $room_id];
    $build['#link_indicator'] = TRUE;

    // Add the building link field value to the #meta array.
    $building = \Drupal::entityTypeManager()->getStorage('building')->load($building_id);
    if ($building) {
      $number = $building->get('number');
      $url = "https://www.facilities.uiowa.edu/building/{$number}";
      $build['#meta']['building_link'] = [
        '#theme' => 'building_link_teaser',
        '#weight' => -1,
        '#building_link' => $url,
        '#building_name' => $building->label(),
      ];
    }

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
