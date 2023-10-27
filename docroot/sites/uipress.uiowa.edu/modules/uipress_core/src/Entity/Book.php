<?php

namespace Drupal\uipress_core\Entity;

use Drupal\uiowa_core\Entity\NodeBundleBase;
use Drupal\uiowa_core\Entity\RendersAsCardInterface;

/**
 * Provides an interface for book entries.
 */
class Book extends NodeBundleBase implements RendersAsCardInterface {

  /**
   * {@inheritdoc}
   */
  public function buildCard(array &$build) {
    parent::buildCard($build);

    // Map the author if the field has been filled,
    // else we want the editor.
    $subtitle = ($this->get('field_book_author')?->get(0)) ? 'field_book_author' : 'field_book_editor';

    // Process additional card mappings.
    $this->mapFieldsToCardBuild($build, [
      '#subtitle' => $subtitle,
      '#meta' => 'field_book_type',
    ]);

    $build['#title_heading_size'] = 'h3';

  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultCardStyles(): array {
    return [
      ...parent::getDefaultCardStyles(),
      'card_media_position' => 'card--layout-right',
      'media_size' => 'media--medium',
      'media_format' => 'media',
    ];
  }

}
