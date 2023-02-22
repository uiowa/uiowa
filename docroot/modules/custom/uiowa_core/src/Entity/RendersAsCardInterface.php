<?php

namespace Drupal\uiowa_core\Entity;

/**
 * Defines the interface for entity types that can render as cards.
 */
interface RendersAsCardInterface {

  /**
   * Set the build to render as a card.
   *
   * @param array $build
   *   The renderable build array.
   */
  public function addCardBuildInfo(array &$build);

  /**
   * Build card teaser render array.
   *
   * @param array $build
   *   The renderable build array.
   */
  public function buildCard(array &$build);

  /**
   * Return an array mapping card style group names to classes.
   *
   * @return array
   *   The styles default card styles.
   */
  public function getDefaultCardStyles(): array;

  /**
   * Add card styles to the build array.
   *
   * @param array $build
   *   The build array that is being updated.
   */
  public function buildCardStyles(array &$build);

}
