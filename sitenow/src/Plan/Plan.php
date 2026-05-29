<?php

namespace SiteNow\Plan;

/**
 * A snapshot of what a command would do.
 *
 * Returned by a command's `decide()` method and consumed by `executePlan()`.
 */
class Plan {

  /**
   * @param string $title
   *   Header line for the rendered plan.
   * @param array $input
   *   Normalized command input (e.g. host, flags).
   * @param array $validation
   *   Validation results: ['overall' => PASS|WARN|FAIL, 'checks' => [...]].
   * @param array $summary
   *   Display rows: [['label' => string, 'value' => string], ...].
   * @param array $context
   *   Command-specific decisions carried for buildSteps().
   */
  public function __construct(
    public readonly string $title,
    public readonly array $input,
    public array $validation,
    public array $summary = [],
    public array $context = [],
  ) {}

  /**
   * Whether validation failed overall.
   */
  public function failed(): bool {
    return ($this->validation['overall'] ?? CheckStatus::Pass) === CheckStatus::Fail;
  }

  /**
   * Whether validation produced a warning overall.
   */
  public function warned(): bool {
    return ($this->validation['overall'] ?? CheckStatus::Pass) === CheckStatus::Warn;
  }

}
