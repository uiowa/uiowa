<?php

namespace SiteNow\Traits;

/**
 * Renders a failed per-site drush result as a one-line reason.
 *
 * Shared by the commands that fan drush out across the fleet, so the way a
 * failure reads stays consistent between them.
 */
trait DescribesDrushFailures {

  /**
   * Summarize why a site failed, from its stderr (or stdout) tail.
   *
   * Leads with drush's own error message so the reason reads plainly; the exit
   * code trails in parentheses as a detail rather than as the headline.
   *
   * @param array{exit: int, output: string, error: string} $result
   *   The per-site result.
   *
   * @return string
   *   A one-line reason.
   */
  protected function failureReason(array $result): string {
    $source = trim($result['error']) !== '' ? $result['error'] : $result['output'];
    $lines = array_filter(array_map('trim', preg_split('/\R/', $source)), fn ($l) => $l !== '');
    $tail = $lines ? end($lines) : '';

    return $tail !== ''
      ? "{$tail} (exit {$result['exit']})"
      : "no error output (exit {$result['exit']})";
  }

}
