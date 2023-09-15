<?php

namespace Drupal\uiowa_core\Entity;

use Drupal\block_content\Entity\BlockContent;
use Drupal\Core\Render\Element;

/**
 * A bundle entity class for card block content.
 */
class Card extends BlockContent implements RendersAsCardInterface {

  use RendersAsCardTrait;

  /**
   * {@inheritdoc}
   */
  public function buildCard(array &$build) {
    $this->buildCardStyles($build);

    // Add fields to card.
    $this->mapFieldsToCardBuild($build, [
      '#media' => 'field_uiowa_card_image',
      '#subtitle' => 'field_uiowa_card_author',
      '#content' => 'field_uiowa_card_excerpt',
    ]);

    // Capture the parts of the URL.
    foreach ([
      'url' => 'url',
      'title' => 'link_text',
    ] as $field_link_prop => $link_prop) {
      if (isset($build['field_uiowa_card_link'][0]["#$field_link_prop"])) {
        $build["#$link_prop"] = $build['field_uiowa_card_link'][0]["#$field_link_prop"];
      }
    }
    unset($build['field_uiowa_card_link']);

    // Handle the title field.
    if (isset($build['field_uiowa_card_title']) && count(Element::children($build['field_uiowa_card_title'])) > 0) {
      $build['#title'] = $build['field_uiowa_card_title'][0]['#text'];
      $build['#title_heading_size'] = $build['field_uiowa_card_title'][0]['#size'];
      unset($build['field_uiowa_card_title']);
    }

    // Pull the button display value from the entity.
    $link_indicator = $this->field_uiowa_card_button_display
      ?->value
      ?? 'Use site default';

    // Check if it is site default.
    if ($link_indicator === 'Use site default') {
      // Set boolean to site default value.
      $link_indicator = \Drupal::config('sitenow_pages.settings')
        ->get('card_link_indicator_display');
    }

    if ($link_indicator === 'Show' || $link_indicator === TRUE) {
      $build['#link_indicator'] = TRUE;
    }
  }

}
