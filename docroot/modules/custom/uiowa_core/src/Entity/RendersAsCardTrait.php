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
    // Check for override styles.
    $override_styles = $build['#override_styles'] ?? [];

    // Loop through combined default and override styles and add them.
    foreach ([
      ...$this->getDefaultCardStyles(),
      ...$override_styles,
    ] as $style) {
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
      // If the prop hasn't been added yet, add it.
      if (!isset($build[$prop])) {
        $build[$prop] = [];
      }
      // For convenience, fields can be passed as strings. Convert strings to
      // an array.
      if (!is_array($fields)) {
        $fields = [$fields];
      }
      // Loop through fields.
      foreach ($fields as $field_name) {
        // If the field exists, it can be rendered, and should not be hidden,
        // add it to the appropriate prop.
        if (isset($build[$field_name])) {
          if (count(Element::children($build[$field_name])) > 0 && !in_array($field_name, $hide_fields)) {
            $build[$prop][$field_name] = $build[$field_name];
          }
          // Unset the field, so it doesn't get accidentally displayed.
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
