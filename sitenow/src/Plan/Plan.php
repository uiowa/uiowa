<?php

namespace SiteNow\Plan;

use Robo\Contract\TaskInterface;

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
   * @param \Robo\Contract\TaskInterface $task
   *   The task that performs the action.
   */
  public function addStep(string $label, TaskInterface $task): void {
    $this->steps[] = ['label' => $label, 'task' => $task];
  }

  /**
   * The ordered steps.
   *
   * @return array
   *   Each entry: ['label' => string, 'task' => TaskInterface].
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
