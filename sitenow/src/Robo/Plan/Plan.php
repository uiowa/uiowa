<?php

namespace SiteNow\Robo\Plan;

/**
 * A decided plan: validated facts ready for display and execution.
 */
final class Plan {

  /**
   * @param string $title
   *   Header line for the rendered plan.
   * @param array $input
   *   Normalized command input (e.g. host, flags).
   * @param array $validation
   *   Validation block: ['overall' => PASS|WARN|FAIL, 'checks' => [...]].
   * @param array $summary
   *   Display rows: [['label' => string, 'value' => string], ...].
   * @param array $context
   *   Command-specific decisions carried for buildSteps() and JSON output.
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
    return ($this->validation['overall'] ?? Precondition::PASS) === Precondition::FAIL;
  }

  /**
   * Whether validation produced a warning overall.
   */
  public function warned(): bool {
    return ($this->validation['overall'] ?? Precondition::PASS) === Precondition::WARN;
  }

}
