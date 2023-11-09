<?php

namespace Drupal\facilities_core\Entity;

use Drupal\uiowa_core\Entity\NodeBundleBase;
use Drupal\uiowa_core\Entity\RendersAsCardInterface;

/**
 * Provides an interface for Facilities artwork entries.
 */
class Artwork extends NodeBundleBase implements RendersAsCardInterface {

  /**
   * {@inheritdoc}
   */
  public function buildCard(array &$build) {
    parent::buildCard($build);

    // Process additional card mappings.
    // @todo Remove when https://github.com/uiowa/uiowa/pull/6769 has merged.
    $this->mapFieldsToCardBuild($build, [
      '#content' => 'body',
    ]);

    $build['#link_indicator'] = TRUE;

  }

}
