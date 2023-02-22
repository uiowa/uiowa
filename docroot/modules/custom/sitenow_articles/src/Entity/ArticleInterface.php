<?php

namespace Drupal\sitenow_articles\Entity;

/**
 * Defines the interface for article methods.
 */
interface ArticleInterface {

  /**
   * Get the render array for a article byline.
   *
   * @param array $build
   *   The build array.
   *
   * @return array
   *   The field build array.
   */
  public function getByline(array $build = []): array;

}
