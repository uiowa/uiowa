<?php

namespace Drupal\sitenow_paragraphs\Entity;

use Drupal\Core\Template\Attribute;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\uiowa_core\Entity\RendersAsCardInterface;
use Drupal\uiowa_core\Entity\RendersAsCardTrait;

/**
 * Provides an interface for paragraph cards on the area of study page entries.
 */
class ParagraphsCard extends Paragraph implements RendersAsCardInterface {

  use RendersAsCardTrait;

  /**
   * {@inheritdoc}
   */
  public function buildCard(array &$build) {
    $this->buildCardStyles($build);

    // Process additional card mappings.
    $this->mapFieldsToCardBuild($build, [
      '#content' => 'field_card_body',
      '#media' => 'field_card_image',
      '#title' => 'field_card_title',
      '#subtitle' => 'field_card_subtitle',
    ]);

  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultCardStyles(): array {
    // Get the paragraph bundle and view mode.
    $bundle = $this->bundle();
    $view_mode = $build['#view_mode'] ?? '';

    $classes = [
      'paragraph',
      'paragraph--type--' . $bundle,
      $view_mode ? 'paragraph--view-mode--' . $view_mode : '',
      !$this->isPublished() ? 'paragraph--unpublished' : '',
    ];

    $attributes = new Attribute([
      'class' => $classes,
    ]);

    return [
      'headline_class' => '',
      'styles' => implode(' ', $attributes->toArray()['class']),
    ];
  }

}
