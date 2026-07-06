<?php

namespace SiteNow\Plan;

/**
 * A snapshot of what a command would do if executed.
 *
 * Returned by a command's `decide()` method and consumed by `executePlan()`.
 */
class Plan {

  /**
   * Ordered actions to run, added via addStep().
   *
   * @var array
   */
  private array $steps = [];

  /**
   * Post-apply guidance lines shown after a successful run.
   *
   * @var string[]
   */
  public array $nextSteps = [];

  /**
   * Constructs a Plan with its decision. Steps are added via addStep().
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
   */
  public function __construct(
    public readonly string $title,
    public readonly array $input,
    public array $validation,
    public array $summary = [],
    public array $context = [],
  ) {}

  /**
   * Add an action to run when the plan is applied.
   *
   * @param string $label
   *   Human-readable description shown in the plan preview.
   * @param \Closure $run
   *   The operation that performs the action: function
   *   (\Symfony\Component\Console\Style\SymfonyStyle $io): void. Throws on
   *   failure; there is no rollback.
   */
  public function addStep(string $label, \Closure $run): void {
    $this->steps[] = ['label' => $label, 'run' => $run];
  }

  /**
   * The ordered steps.
   *
   * @return array
   *   Each entry: ['label' => string, 'run' => \Closure].
   */
  public function steps(): array {
    return $this->steps;
  }

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
