<?php

namespace Drupal\sitenow_p2lb;

use Drupal\sitenow_pages\Entity\Page;

/**
 * A class for managing converters.
 */
class P2LbConverterManager implements P2LbConverterManagerInterface {

  /**
   * {@inheritdoc}
   */
  public function createConverter(Page $page): P2LbConverter {
    return new P2LbConverter($page);
  }

}
