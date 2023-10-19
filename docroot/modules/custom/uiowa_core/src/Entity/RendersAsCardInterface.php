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
  public function addCardBuildInfo(array &$build): void;

  /**
   * Build card teaser render array.
   *
   * @param array $build
   *   The renderable build array.
   */
  public function buildCard(array &$build): void;

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
  public function buildCardStyles(array &$build): void;

  /**
   * Determine whether a view mode should be rendered as a card.
   *
   * @param string $view_mode
   *   The view mode being checked.
   *
   * @return bool
   *   Whether the view mode should render as a card.
   */
  public function viewModeShouldRenderAsCard(string $view_mode): bool;

}
