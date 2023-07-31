<?php

namespace Drupal\sitenow_p2lb;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\sitenow_pages\Entity\Page;

/**
 * A helper class for P2LB.
 */
class P2LbHelper {

  use StringTranslationTrait;

  /**
   * Compare a string of text using two different formats.
   *
   * @param string $text
   *   The text being tested.
   * @param string $format_one
   *   The first format.
   * @param string $format_two
   *   The second format.
   *
   * @return bool
   */
  public static function formattedTextIsEquivalent(string $text, string $format_one, string $format_two): bool {
    return check_markup($text, $format_one) === check_markup($text, $format_two);
  }
}
