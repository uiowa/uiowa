<?php

namespace SiteNow\Plan;

/**
 * Shared plan execution for Robo commands: render, confirm, apply.
 *
 * A using command produces a Plan and passes it to `executePlan()`.
 */
trait PlanTrait {

  /**
   * Render a plan to the console.
   *
   * @param string $title
   *   Short header line, e.g. "uiowa:multisite:create newsite.uiowa.edu".
   * @param array $summary
   *   Optional key/value rows shown before validation, assembled by the
   *   calling command. Each entry: ['label' => string, 'value' => string].
   * @param array $validation
   *   Output of runChecks().
   * @param array $steps
   *   Each entry has a 'label' key. Empty array renders no Actions block.
   */
  protected function renderPlan(
    string $title,
    array $summary,
    array $validation,
    array $steps = [],
  ): void {
    $this->output()->writeln('');
    $this->output()->writeln('<options=bold>─────────────────────────────────────────────────────────────────</>');
    $this->output()->writeln("<options=bold>  {$title}</>");
    $this->output()->writeln('<options=bold>─────────────────────────────────────────────────────────────────</>');
    $this->output()->writeln('');

    foreach ($summary as $row) {
      $label = str_pad($row['label'] . ':', 14);
      $this->output()->writeln("  <options=bold>{$label}</> {$row['value']}");
    }

    if ($summary) {
      $this->output()->writeln('');
    }

    $overall = $validation['overall'];
    $color = match($overall) {
      CheckStatus::Fail => 'red',
      CheckStatus::Warn => 'yellow',
      default => 'green',
    };

    $this->output()->writeln("  <options=bold>Validation:</> <fg={$color}>{$overall->value}</>");

    $non_pass = array_filter($validation['checks'], fn($c) => $c['status'] !== CheckStatus::Pass);
    foreach ($non_pass as $check) {
      $icon = $check['status'] === CheckStatus::Fail ? '<fg=red>✗</>' : '<fg=yellow>!</>';
      $this->output()->writeln("    {$icon} [{$check['status']->value}] {$check['message']}");
    }

    if ($overall === CheckStatus::Fail) {
      $this->output()->writeln('');
      return;
    }

    $this->output()->writeln('');

    if ($steps) {
      $this->output()->writeln('  <options=bold>Actions:</>');
      foreach ($steps as $step) {
        $this->output()->writeln("    · {$step['label']}");
      }
      $this->output()->writeln('');
    }
  }

  /**
   * Dispatch a decided plan: render it, then exit or apply based on mode.
   *
   * All command-specific data comes from the Plan.
   *
   * @param \SiteNow\Plan\Plan $plan
   *   The decided plan.
   * @param array $options
   *   Command options. Reads 'dry-run' and 'yes'.
   */
  protected function executePlan(Plan $plan, array $options): void {
    // Validation failed: show the failing checks and stop before any work.
    if ($plan->failed()) {
      $this->renderPlan($plan->title, [], $plan->validation);
      return;
    }

    $this->renderPlan($plan->title, $plan->summary, $plan->validation, $plan->steps);

    // Dry run: the plan was previewed; make no changes.
    if (!empty($options['dry-run'])) {
      return;
    }

    // --yes applies without prompting, but never past a warning.
    if (!empty($options['yes'])) {
      if ($plan->warned()) {
        $this->io()->error('Aborting: --yes was passed but validation has a WARN. Resolve the warning or run interactively.');
        return;
      }
    }
    elseif ($this->promptApply() === 'n') {
      $this->say('Aborted.');
      return;
    }

    $this->applyPlan($plan);
  }

  /**
   * Run a plan's steps in a Robo collection with rollback on failure.
   *
   * @param \SiteNow\Plan\Plan $plan
   *   The plan whose steps to run.
   */
  protected function applyPlan(Plan $plan): void {
    // Load each step's task into one collection so a mid-run failure rolls
    // back the tasks that already ran.
    $collection = $this->collectionBuilder();
    foreach ($plan->steps as $step) {
      $collection->addTask($step['task']);
    }

    $result = $collection->run();

    if (!$result->wasSuccessful()) {
      $this->io()->error('Plan execution failed. Rolled back where possible.');
      return;
    }

    $this->say('<info>Done.</info>');

    if ($plan->nextSteps) {
      $this->say('Next steps:');
      $this->io()->listing($plan->nextSteps);
    }
  }

  /**
   * Prompt for apply/abort. Returns 'y' or 'n'.
   */
  protected function promptApply(): string {
    while (TRUE) {
      $answer = $this->ask('Apply? [<options=bold>y</>]es / [<options=bold>n</>]o: ');
      $answer = strtolower(trim($answer ?? ''));
      if (in_array($answer, ['y', 'yes', ''])) {
        return 'y';
      }
      if (in_array($answer, ['n', 'no'])) {
        return 'n';
      }
      $this->say('Please enter y or n.');
    }
  }

  /**
   * Evaluate each check and aggregate the results.
   *
   * Returns the per-check results keyed by name, plus an overall status that
   * is the worst of the individual statuses.
   *
   * @param \SiteNow\Plan\Check[] $checks
   *   Checks evaluated in declared order.
   *
   * @return array
   *   ['overall' => CheckStatus, 'checks' => [name => [status, message, context]]]
   */
  protected function runChecks(array $checks): array {
    $result = ['checks' => [], 'overall' => CheckStatus::Pass];

    foreach ($checks as $check) {
      $outcome = $check->evaluate();
      $result['checks'][$check->name] = [
        'status' => $outcome->status,
        'message' => $outcome->message,
        'context' => $outcome->context,
      ];

      if ($this->statusRank($outcome->status) > $this->statusRank($result['overall'])) {
        $result['overall'] = $outcome->status;
      }
    }

    return $result;
  }

  /**
   * Merge a second set of validation results into a base.
   *
   * The merged overall status is the worse of the two.
   *
   * @param array $base
   *   The base validation results.
   * @param array $extra
   *   Additional checks to fold in.
   *
   * @return array
   *   The merged validation results.
   */
  protected function mergeValidation(array $base, array $extra): array {
    $base['checks'] = array_merge($base['checks'], $extra['checks']);
    if ($this->statusRank($extra['overall']) > $this->statusRank($base['overall'])) {
      $base['overall'] = $extra['overall'];
    }
    return $base;
  }

  /**
   * Severity rank for a status. Higher is worse.
   *
   * @param \SiteNow\Plan\CheckStatus $status
   *   The status to rank.
   *
   * @return int
   *   0 for Pass, 1 for Warn, 2 for Fail.
   */
  private function statusRank(CheckStatus $status): int {
    return match ($status) {
      CheckStatus::Pass => 0,
      CheckStatus::Warn => 1,
      CheckStatus::Fail => 2,
    };
  }

}
