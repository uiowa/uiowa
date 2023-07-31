<?php

namespace Drupal\sitenow_p2lb;

class P2LbHelper {
  public static function formattedTextIsEquivalent($text, $format_one, $format_two) {
    return check_markup($text, $format_one) === check_markup($text, $format_two);
  }
}
