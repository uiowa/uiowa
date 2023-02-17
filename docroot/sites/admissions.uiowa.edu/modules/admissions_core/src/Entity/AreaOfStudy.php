<?php

namespace Drupal\admissions_core\Entity;

use Drupal\uiowa_core\Entity\NodeBundleBase;
use Drupal\uiowa_core\Entity\TeaserCardInterface;

/**
 * Provides an interface for student profile page entries.
 */
class AreaOfStudy extends NodeBundleBase implements TeaserCardInterface {

  /**
   * {@inheritdoc}
   */
  public function buildCard(array &$build) {
    parent::buildCard($build);

    // Process additional card mappings.
  }

}
