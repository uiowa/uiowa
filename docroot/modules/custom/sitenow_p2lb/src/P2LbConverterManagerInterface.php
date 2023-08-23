<?php

namespace Drupal\sitenow_p2lb;

use Drupal\sitenow_pages\Entity\Page;

interface P2LbConverterManagerInterface {

  /**
   * Instantiates a converter object for a page.
   *
   * @param \Drupal\sitenow_pages\Entity\Page $page
   *   The page being converted.
   *
   * @return \Drupal\sitenow_p2lb\P2LbConverter
   *   The converter object.
   */
  public function createConverter(Page $page): P2LbConverter;
}
