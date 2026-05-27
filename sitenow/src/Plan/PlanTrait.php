<?php

namespace SiteNow\Plan;

/**
 * Plan-then-execute orchestration for Robo commands.
 *
 * Owns the generic loop: run declared checks, render the plan, dispatch the
 * execution mode, and run the step collection. A calling command supplies the
 * domain-specific pieces: a decide() method that returns a Plan and a
 * buildSteps() method that returns label/task pairs.
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
      Precondition::FAIL => 'red',
      Precondition::WARN => 'yellow',
      default => 'green',
    };

    $this->output()->writeln("  <options=bold>Validation:</> <fg={$color}>{$overall}</>");

    $non_pass = array_filter($validation['checks'], fn($c) => $c['status'] !== Precondition::PASS);
    foreach ($non_pass as $name => $check) {
      $icon = $check['status'] === Precondition::FAIL ? '<fg=red>✗</>' : '<fg=yellow>!</>';
      $this->output()->writeln("    {$icon} [{$check['status']}] {$name}: {$check['message']}");
    }

    if ($overall === Precondition::FAIL) {
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

    $this->output()->writeln('<options=bold>─────────────────────────────────────────────────────────────────</>');
    $this->output()->writeln('');
  }

  /**
   * Run a decided plan through the standard mode dispatch.
   *
   * Handles every execution mode in one place: FAIL short-circuit, JSON
   * output, dry-run, the --yes warning gate, and the interactive prompt.
   * Steps are built lazily so no work happens on paths that exit early.
   *
   * @param \SiteNow\Plan\Plan $plan
   *   The decided plan.
   * @param array $options
   *   Command options. Reads 'output', 'dry-run', and 'yes'.
   * @param callable $build_steps
   *   Returns the ordered steps when invoked:
   *   function (): array of ['label' => string, 'task' => TaskInterface].
   */
  protected function executePlan(Plan $plan, array $options, callable $build_steps): void {
    if ($plan->failed()) {
      $this->renderPlan($plan->title, [], $plan->validation);
      return;
    }

    $steps = $build_steps();

    if (($options['output'] ?? '') === 'json') {
      $payload = [
        'input' => $plan->input,
        'decisions' => $plan->context,
        'validation' => $plan->validation,
        'actions_summary' => array_column($steps, 'label'),
      ];
      $this->output()->writeln(json_encode($payload, JSON_PRETTY_PRINT));
      return;
    }

    $this->renderPlan($plan->title, $plan->summary, $plan->validation, $steps);

    if (!empty($options['dry-run'])) {
      return;
    }

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

    $this->applyPlan($steps);
  }

  /**
   * Run plan steps in a Robo collection with rollback on failure.
   *
   * @param array $steps
   *   Ordered steps: [['label' => string, 'task' => TaskInterface], ...].
   */
  protected function applyPlan(array $steps): void {
    $collection = $this->collectionBuilder();
    foreach ($steps as $step) {
      $collection->addTask($step['task']);
    }

    $result = $collection->run();

    if (!$result->wasSuccessful()) {
      $this->io()->error('Plan execution failed. Rolled back where possible.');
      return;
    }

    $this->say('<info>Done.</info>');
    $this->afterApply($steps);
  }

  /**
   * Hook invoked after a successful apply.
   *
   * Commands override this to print domain-specific follow-up guidance.
   *
   * @param array $steps
   *   The steps that were executed.
   */
  protected function afterApply(array $steps): void {}

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
   * Evaluate declared checks into a serializable validation block.
   *
   * @param \SiteNow\Plan\Check[] $checks
   *   Checks evaluated in declared order.
   *
   * @return array
   *   ['overall' => PASS|WARN|FAIL, 'checks' => [name => [status, message, context]]]
   */
  protected function runChecks(array $checks): array {
    $result = ['checks' => [], 'overall' => Precondition::PASS];

    foreach ($checks as $check) {
      $precondition = $check->evaluate();
      $result['checks'][$precondition->name] = [
        'status' => $precondition->status,
        'message' => $precondition->message,
        'context' => $precondition->context,
      ];

      if ($precondition->isFail()) {
        $result['overall'] = Precondition::FAIL;
      }
      elseif ($precondition->isWarn() && $result['overall'] !== Precondition::FAIL) {
        $result['overall'] = Precondition::WARN;
      }
    }

    return $result;
  }

  /**
   * Merge a second validation block into a base, keeping the worst status.
   *
   * @param array $base
   *   The base validation block.
   * @param array $extra
   *   Additional checks to fold in.
   *
   * @return array
   *   The merged validation block.
   */
  protected function mergeValidation(array $base, array $extra): array {
    $rank = [Precondition::PASS => 0, Precondition::WARN => 1, Precondition::FAIL => 2];
    $base['checks'] = array_merge($base['checks'], $extra['checks']);
    if ($rank[$extra['overall']] > $rank[$base['overall']]) {
      $base['overall'] = $extra['overall'];
    }
    return $base;
  }

}
