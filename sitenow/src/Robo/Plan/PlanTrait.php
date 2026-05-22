<?php

namespace SiteNow\Robo\Plan;

/**
 * Plan-then-execute UX helpers for Robo commands.
 *
 * Provides plan rendering, validation aggregation, and an apply prompt.
 * The calling command supplies domain-specific titles, summary rows, and steps.
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
   *   Output of buildValidation().
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
   * Convert an array of Precondition objects into a serializable validation
   * summary with an 'overall' status and per-check details.
   *
   * @param Precondition[] $checks
   *   Keyed by check name.
   *
   * @return array
   *   ['overall' => PASS|WARN|FAIL, 'checks' => [name => [status, message, context]]]
   */
  protected function buildValidation(array $checks): array {
    $result = ['checks' => [], 'overall' => Precondition::PASS];

    foreach ($checks as $name => $check) {
      $result['checks'][$name] = [
        'status' => $check->status,
        'message' => $check->message,
        'context' => $check->context,
      ];

      if ($check->isFail()) {
        $result['overall'] = Precondition::FAIL;
      }
      elseif ($check->isWarn() && $result['overall'] !== Precondition::FAIL) {
        $result['overall'] = Precondition::WARN;
      }
    }

    return $result;
  }

}
