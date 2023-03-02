<?php

namespace Drupal\uiowa_core\Entity;

use Drupal\block_content\Entity\BlockContent;
use Drupal\Core\Render\Element;
use Drupal\uiowa_core\Element\Card;

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

    // @todo Capture the parts of the URL. This isn't working with
    //   caching.
    foreach ([
      'url' => 'url',
      'title' => 'link_text',
    ] as $field_link_prop => $link_prop) {
      if (isset($build['field_uiowa_event_link'][0]["#$field_link_prop"])) {
        $build["#$link_prop"] = $build['field_uiowa_event_link'][0]["#$field_link_prop"];
      }
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
      'card_media_position' => 'card--layout-left',
      'media_format' => 'media--circle',
      'media_size' => 'media--small',
    ];
  }

}
