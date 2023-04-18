<?php

namespace Drupal\uiowa_core\Entity;

use Drupal\block_content\Entity\BlockContent;
use Drupal\Core\Render\Element;

/**
 * A bundle entity class for card block content.
 */
class Event extends BlockContent implements RendersAsCardInterface {

  use RendersAsCardTrait;

  /**
   * {@inheritdoc}
   */
  public function buildCard(array &$build) {
    $this->buildCardStyles($build);

    // Add fields to card.
    $this->mapFieldsToCardBuild($build, [
      '#media' => 'field_uiowa_event_image',
      '#subtitle' => 'field_uiowa_event_date',
      '#meta' => [
        'field_uiowa_event_icon',
        'field_uiowa_event_location',
      ],
    ]);

    // Get the link.
    if (isset($build['field_uiowa_event_link'][0]['#url'])) {
      $build['#url'] = $build['field_uiowa_event_link'][0]['#url'];
    }
    unset($build['field_uiowa_event_link']);

    // Handle the title field.
    if (isset($build['field_uiowa_event_title']) && count(Element::children($build['field_uiowa_event_title'])) > 0) {
      $build['#title'] = $build['field_uiowa_event_title'][0]['#text'];
      $build['#title_heading_size'] = $build['field_uiowa_event_title'][0]['#size'];
      unset($build['field_uiowa_event_title']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultCardStyles(): array {
    return [
      'card_headline_style' => 'headline--serif',
      'card_media_position' => 'card--layout-left',
      'media_format' => 'media--circle media--border',
      'media_size' => 'media--small',
      'border' => 'borderless',
    ];
  }

}
