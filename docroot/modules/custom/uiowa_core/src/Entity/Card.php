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
    $path = isset($build['field_uiowa_card_link'][0]['#url']) ? $build['field_uiowa_card_link'][0]['#url']->toString() : NULL;
    $text = $build['field_uiowa_card_link'][0]['#title'] ?? NULL;

    if ($path === NULL || $text === NULL) {
      $build['#url'] = '';
      $build['#link_text'] = '';
    }
    else {
      $is_nolink = str_starts_with($path, 'route:<nolink>');
      $is_front = str_starts_with($path, '<front>');
      $is_external = UrlHelper::isExternal($path);

      switch (TRUE) {
        case $is_nolink:
          $build['#url'] = '';
          $build['#link_text'] = $text;
          break;

        case $is_front:
          $build['#url'] = '/' . substr($path, strlen('<front>'));
          $build['#link_text'] = $text;
          break;

        case $is_external:
          $build['#url'] = $path;
          $build['#link_text'] = str_starts_with($text, 'http') ? NULL : $text;
          break;

        default:
          $internal_path = str_starts_with($path, '/') ? $path : '/' . $path;
          $alias = \Drupal::service('path_alias.manager')->getAliasByPath($internal_path);

          if ($alias) {
            $build['#url'] = $alias;
            $build['#link_text'] = str_starts_with($text, '/') ? NULL : $text;
          }
          else {
            $build['#url'] = $path;
            $build['#link_text'] = $text;
          }
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
