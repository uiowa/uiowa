<?php

namespace Drupal\uipress_core\Entity;

use Drupal\uiowa_core\Entity\NodeBundleBase;
use Drupal\uiowa_core\Entity\RendersAsCardInterface;
use function PHPUnit\Framework\isEmpty;

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
    $subtitle = ($this->get('field_book_author')?->isEmpty()) ? 'field_book_editor' : 'field_book_author';

    // Process additional card mappings.
    $this->mapFieldsToCardBuild($build, [
      '#subtitle' => $subtitle,
      '#meta' => 'field_book_type',
    ]);

  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultCardStyles(): array {
    $default_classes = [
      ...parent::getDefaultCardStyles(),
      'card_media_position' => 'card--layout-right',
      'media_size' => 'media--medium',
      'media_format' => 'media',
    ];

    return $default_classes;
  }

}
