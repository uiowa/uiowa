<?php

namespace Drupal\uiowa_core\Entity;

interface TeaserCardInterface {

  /**
   * Set the build to render as a card.
   *
   * @param array $build
   */
  public function addCardBuildInfo(array &$build);

  /**
   * Build card teaser render array.
   *
   * @param $build
   */
  public function buildCard(&$build);

  /**
   * Return an array mapping card style group names to classes.
   *
   * @return array
   */
  public function getDefaultStyles(): array;
}
