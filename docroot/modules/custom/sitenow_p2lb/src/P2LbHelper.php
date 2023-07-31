<?php

namespace Drupal\sitenow_p2lb;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\sitenow_pages\Entity\Page;

class P2LbHelper {

  use StringTranslationTrait;
  public static function formattedTextIsEquivalent($text, $format_one, $format_two) {
    return check_markup($text, $format_one) === check_markup($text, $format_two);
  }
}
