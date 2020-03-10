<?php

namespace Drupal\brand_core;

use EasySVG;

/**
 * BrandSVG.
 */
class BrandSVG extends EasySVG {

  /**
   * Add a rect to the SVG.
   *
   * @param array $attributes
   *
   * @return SimpleXMLElement
   */
  public function addRect($attributes = []) {
    $rect = $this->svg->addChild('rect');
    foreach ($attributes as $key => $value) {
      $rect->addAttribute($key, $value);
    }
    return $rect;
  }

}
