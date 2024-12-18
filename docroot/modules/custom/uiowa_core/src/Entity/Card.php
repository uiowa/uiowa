<?php

namespace Drupal\uiowa_core\Entity;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Render\Element;
use Drupal\block_content\Entity\BlockContent;

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
      '#media' => [
        'field_uiowa_card_image',
        'field_icon',
      ],
      '#subtitle' => 'field_uiowa_card_author',
      '#content' => 'field_uiowa_card_excerpt',
    ]);

    if (isset($build['field_uiowa_card_link'][0])) {
      // Capture the parts of the URL.
      $url = $build['field_uiowa_card_link'][0]['#url'] ?? NULL;
      $title = $build['field_uiowa_card_link'][0]['#title'] ?? NULL;

      // Only process if both url and title are not null.
      if ($url && $title) {
        $url = $url->toString();
        $build['#link_text'] = $title;

        // Check if the link is an external URL.
        if (UrlHelper::isExternal($url)) {
          // For external links, set the URL directly.
          $build['#url'] = $url;
          // Set link text to null to prevent displaying full URL as link text so that circle button can be used.
          $build['#link_text'] = str_starts_with($title, 'http') ? NULL : $title;
        }
        else {
          $internal_path = str_starts_with($url, '/') ? $url : '/' . $url;
          $alias = \Drupal::service('path_alias.manager')->getAliasByPath($internal_path);

          $build['#url'] = $alias ?: $url;
          // Set link text to null to prevent displaying full URL as link text so that circle button can be used.
          $build['#link_text'] = str_starts_with($title, '/') ? NULL : $title;
        }
      }

      unset($build['field_uiowa_card_link']);
    }

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
