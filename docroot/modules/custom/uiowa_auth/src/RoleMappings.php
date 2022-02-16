<?php

namespace Drupal\uiowa_auth;

/**
 * A utility class to assist with role mappings.
 */
class RoleMappings {

  /**
   * Static class.
   */
  public function __construct() {}

  /**
   * Convert string of line-break delimited role mappings to array.
   *
   * @param string $mappings
   *   String of mappings delimited by PHP_EOL.
   *
   * @return array
   *   Array of mappings.
   */
  public static function textToArray($mappings) {
    $mappings = explode(PHP_EOL, $mappings);
    $mappings = array_filter($mappings);
    $mappings = array_map('trim', $mappings);
    return $mappings;
  }

  /**
   * Convert array of role mappings to line-break delimited string.
   *
   * @param array $mappings
   *   Array of role mappings.
   *
   * @return string
   *   Line-break delimited string of role mappings.
   */
  public static function arrayToText(array $mappings) {
    $text = '';

    foreach ($mappings as $mapping) {
      [$rid, $attr, $value] = explode('|', $mapping);
      $text .= "{$rid}|{$attr}|{$value}";
      $text .= PHP_EOL;
    }

    return rtrim($text);
  }

  /**
   * Generator to yield properly keyed array of role mappings.
   *
   * @param array $mappings
   *   Array of role mappings.
   */
  public static function generate(array $mappings) {
    foreach ($mappings as $mapping) {
      [$rid, $attr, $value] = explode('|', $mapping);

      yield [
        'rid' => $rid,
        'attr' => $attr,
        'value' => $value,
      ];
    }
  }

}
