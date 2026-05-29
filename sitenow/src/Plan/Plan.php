<?php

namespace SiteNow\Plan;

/**
 * A snapshot of what a command would do if executed.
 *
 * Returned by a command's `decide()` method and consumed by `executePlan()`.
 */
class Plan {

  /**
   * Constructs a Plan.
   *
   * The first fields are the decision (durable, what a future paper trail
   * would record). The last two are execution detail, populated only when
   * validation passes and left empty on a failed plan.
   *
   * @param string $title
   *   Header line for the rendered plan.
   * @param array $input
   *   Normalized command input (e.g. host, flags).
   * @param array $validation
   *   Validation results: ['overall' => PASS|WARN|FAIL, 'checks' => [...]].
   * @param array $summary
   *   Display rows: [['label' => string, 'value' => string], ...].
   * @param array $context
   *   Command-specific decisions (e.g. the selected application).
   * @param array $steps
   *   Ordered actions to run: [['label' => string, 'task' => TaskInterface], ...].
   * @param array $nextSteps
   *   Post-apply guidance lines shown after a successful run.
   */
  public function __construct(
    public readonly string $title,
    public readonly array $input,
    public array $validation,
    public array $summary = [],
    public array $context = [],
    public array $steps = [],
    public array $nextSteps = [],
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
