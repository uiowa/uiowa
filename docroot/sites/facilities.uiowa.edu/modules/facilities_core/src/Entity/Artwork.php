<?php

namespace Drupal\facilities_core\Entity;

use Drupal\uiowa_core\Entity\NodeBundleBase;
use Drupal\uiowa_core\Entity\RendersAsCardInterface;

/**
 * Provides an interface for Facilities artwork entries.
 */
class Artwork extends NodeBundleBase implements RendersAsCardInterface {

  /**
   * {@inheritdoc}
   */
  public function buildCard(array &$build) {
    parent::buildCard($build);

    // Process additional card mappings.
    $this->mapFieldsToCardBuild($build, [
      '#content' => 'field_artwork_status',
      '#media' => 'field_gallery_images',
    ]);

    // Combine 'field_artwork_artist' and 'field_artwork_year' into '#subtitle'.
    $artist_field = $this->get('field_artwork_artist');
    $year_field = $this->get('field_artwork_year');

    if (!empty($artist_field) && !empty($year_field)) {
      // Retrieving the entity reference target ID.
      $artist_target_id = $artist_field->getString();

      // Load the referenced "person" entity.
      $artist_entity = \Drupal::entityTypeManager()->getStorage('node')->load($artist_target_id);

      if (!empty($artist_entity) && $artist_entity->getType() === 'person') {
        // Access the title or name of the referenced "person" entity directly.
        // Use 'label()' method to get the title/name.
        $artist_name = $artist_entity->label();

        $year_value = $year_field->getString();

        if (!empty($artist_name) && !empty($year_value)) {
          $subtitle = $artist_name . ', ' . $year_value;
          $build['#subtitle'] = $subtitle;
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultCardStyles(): array {
    return [
      ...parent::getDefaultCardStyles(),
      'card_media_position' => 'card--stacked',
      'card_headline_style' => '',
      'media_size' => 'media--large',
      'border' => '',
    ];
  }

}
