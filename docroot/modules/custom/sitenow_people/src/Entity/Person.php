<?php

namespace Drupal\sitenow_people\Entity;

use Drupal\uiowa_core\Entity\NodeBundleBase;
use Drupal\uiowa_core\Entity\TeaserCardInterface;

/**
 * Provides an interface for person entries.
 */
class Person extends NodeBundleBase implements TeaserCardInterface {

  /**
   * {@inheritdoc}
   */
  public function buildCard(array &$build) {
    parent::buildCard($build);

    // Handle link directly to source functionality.
    $build['#url'] = $this->generateNodeLink('field_person_website_link_direct', 'field_person_website');

    // Process additional card mappings.
    $this->mapFieldsToCardBuild($build, [
      '#subtitle' => 'field_person_position',
      '#meta' => ['field_person_email', 'field_person_phone'],
    ]);

    // Add view specific classes.
    if (isset($this->view)) {
      if ($this->view->id() === 'people') {
        $media_attributes = [
          'card--list',
        ];
        $build['#attributes']->addClass($media_attributes);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultStyles(): array {
    return [
      ...parent::getDefaultStyles(),
      'card_media_position' => 'card--layout-left',
      'media_border' => 'media--border',
      'media_format' => 'media--circle',
    ];
  }

}
