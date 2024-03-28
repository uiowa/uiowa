<?php

namespace Drupal\uiowa_core\Entity;

use Drupal\block_content\Entity\BlockContent;
use Drupal\Component\Utility\UrlHelper;
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
    $path = $build['field_uiowa_card_link'][0]["#url"]->toString();
    $text = $build['field_uiowa_card_link'][0]["#title"];
    // Check if the URL is external.
    if (UrlHelper::isExternal($path)) {
      // If it's external, set the link text and URL to the path itself.
      if (str_starts_with($path, 'http') && str_starts_with($text, 'http')) {
        // Assign $path, not $alias.
        $build["#url"] = $path;
        unset($build["#link_text"]);
      }
      else {
        // If neither $path nor $text starts with 'https', set the link text and URL to the path itself.
        $build["#url"] = $path;
        $build["#link_text"] = $text;
      }
    }
    else {
      // If it's an internal URL, attempt to resolve the alias.
      $alias = \Drupal::service('path_alias.manager')->getAliasByPath($path);

      if ($alias) {
        // If an alias exists, set the alias as the link text and URL.
        // If $alias starts with "/", then set the URL and unset the link_text.
        if (str_starts_with($alias, '/') && str_starts_with($text, '/')) {
          $build["#url"] = $alias;
          unset($build["#link_text"]);
        }
        else {
          // If $alias doesn't start with "/", set the URL and link_text normally.
          $build["#url"] = $alias;
          $build["#link_text"] = $text;
        }
      }

      else {
        // If no alias exists, set the link text and URL to the path itself.
        $build["#url"] = $path;
        $build["#link_text"] = $text;
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
