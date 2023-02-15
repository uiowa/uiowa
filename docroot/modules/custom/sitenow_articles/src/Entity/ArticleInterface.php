<?php

namespace Drupal\sitenow_articles\Entity;

interface ArticleInterface {

  /**
   * Get the render array for a article byline.
   *
   * @return array
   */
  public function getByline(): array;
}
