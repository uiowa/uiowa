<?php

namespace Drupal\uiowa_core\Entity;

use Drupal\Core\Render\Element;

/**
 * Provides functionality related to rendering entities as cards.
 */
trait RendersAsCardTrait {

  /**
   * {@inheritdoc}
   */
  public function addCardBuildInfo(array &$build) {
    // Set the type to card.
    $build['#type'] = 'card';

    // If there is an existing '#theme' setting, unset it.
    if (isset($build['#theme'])) {
      unset($build['#theme']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultCardStyles(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildCardStyles(array &$build) {
    $override_styles = $build['#override_styles'] ?? [];

    $default_styles = $this->getDefaultCardStyles();
    $styles = [
      ...$default_styles,
      ...$override_styles,
    ];
    foreach ($styles as $style) {
      $build['#attributes']['class'][] = $style;
    }
  }

  /**
   * Map build fields to card properties.
   *
   * @param array $build
   *   A renderable array representing the entity content.
   * @param array $mapping
   *   Array of field names.
   */
  protected function mapFieldsToCardBuild(array &$build, array $mapping): void {
    $hide_fields = $build['#hide_fields'] ?? [];

    // Map fields to the card parts.
    foreach ($mapping as $prop => $fields) {
      if (!is_array($fields)) {
        $fields = [$fields];
      }
      if (!isset($build[$prop])) {
        $build[$prop] = [];
      }
      foreach ($fields as $field_name) {
        // @todo Refine this to remove fields if they are empty.
        if (isset($build[$field_name]) && count(Element::children($build[$field_name])) > 0) {
          if (!in_array($field_name, $hide_fields)) {
            $build[$prop][$field_name] = $build[$field_name];
          }
          unset($build[$field_name]);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function viewModeShouldRenderAsCard(string $view_mode): bool {
    if (empty($this->getCardViewModes())) {
      return TRUE;
    }

    return in_array($view_mode, $this->getCardViewModes());
  }

  /**
   * Get view modes that should be rendered as a card.
   *
   * @return string[]
   *   The list of view modes.
   */
  protected function getCardViewModes(): array {
    return [];
  }

}
