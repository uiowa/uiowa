<?php

namespace Drupal\sitenow_pages\Entity;

use Drupal\uiowa_core\Entity\NodeBundleBase;

/**
 * Provides an interface for page entries.
 */
class Page extends NodeBundleBase {

  /**
   * {@inheritdoc}
   */
  protected $configSettings = 'sitenow_pages.settings';

}
