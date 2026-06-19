<?php

namespace SiteNow\Traits;

/**
 * Parses comma-separated console option values into trimmed lists.
 */
trait ParsesListOptions {

  /**
   * Split a comma-separated option value into a trimmed list.
   *
   * @param string $value
   *   The raw option value.
   *
   * @return array
   *   Trimmed, non-empty parts; empty array when the value is empty.
   */
  private function parseList(string $value): array {
    if ($value === '') {
      return [];
    }
    return array_values(array_filter(array_map('trim', explode(',', $value)), fn($v) => $v !== ''));
  }

}
