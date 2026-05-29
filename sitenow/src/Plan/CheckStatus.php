<?php

namespace SiteNow\Plan;

/**
 * The outcome status of a validation check.
 */
enum CheckStatus: string {

  case Pass = 'PASS';
  case Warn = 'WARN';
  case Fail = 'FAIL';

  /**
   * Severity rank for comparing statuses. Higher is worse.
   *
   * @return int
   *   0 for Pass, 1 for Warn, 2 for Fail.
   */
  public function rank(): int {
    return match ($this) {
      self::Pass => 0,
      self::Warn => 1,
      self::Fail => 2,
    };
  }

}
