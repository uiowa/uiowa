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

    $reason = $this->styledBlock($source);

    if ($reason === NULL) {
      $lines = array_filter(array_map('trim', preg_split('/\R/', $source)), fn ($l) => $l !== '');
      $reason = $lines ? end($lines) : '';
    }

    return $reason !== ''
      ? "{$reason} (exit {$result['exit']})"
      : "no error output (exit {$result['exit']})";
  }

  /**
   * Rejoin a Symfony error block into the single sentence it was written as.
   *
   * A child that reports through SymfonyStyle wraps its message to the terminal
   * width, so reading the last line alone quotes a fragment starting mid
   * sentence. Only uppercase markers are matched, which is what Symfony emits;
   * drush's own lowercase `[error]` lines are left to the tail behaviour they
   * have always had.
   *
   * @param string $source
   *   The captured output.
   *
   * @return string|null
   *   The rejoined message, or NULL when there is no block to rejoin.
   */
  private function styledBlock(string $source): ?string {
    $lines = preg_split('/\R/', $source) ?: [];
    $start = NULL;

    // The last block wins, matching the tail behaviour: when a child reports
    // more than once, the final word on why it stopped is the useful one.
    foreach ($lines as $i => $line) {
      if (preg_match('/^\s*\[[A-Z]+\]\s/', $line) === 1) {
        $start = $i;
      }
    }

    if ($start === NULL) {
      return NULL;
    }

    $block = [preg_replace('/^\s*\[[A-Z]+\]\s+/', '', $lines[$start])];

    // A styled block is padded to a rectangle and ends at the first blank line.
    for ($i = $start + 1; $i < count($lines); $i++) {
      if (trim($lines[$i]) === '') {
        break;
      }

      $block[] = $lines[$i];
    }

    return trim(preg_replace('/\s+/', ' ', implode(' ', $block)));
  }

}
