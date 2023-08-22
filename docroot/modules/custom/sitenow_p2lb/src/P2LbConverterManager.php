<?php

namespace Drupal\sitenow_p2lb;

use Drupal\sitenow_pages\Entity\Page;

class P2LbConverterManager {

  public function createConverter(Page $page) {
    return new P2LbConverter($page);
  }
}
