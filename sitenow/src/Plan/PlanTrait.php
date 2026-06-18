<?php

namespace SiteNow\Plan;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Shared plan execution for commands: render, confirm, apply.
 *
 * A using command produces a Plan and passes it to `executePlan()` along with
 * its SymfonyStyle. The validation aggregation helpers (`runChecks()`,
 * `mergeValidation()`) are pure and framework-independent.
 */
trait PlanTrait {

  /**
   * Render a plan to the console.
   *
   * @param \Symfony\Component\Console\Style\SymfonyStyle $io
   *   The output style.
   * @param string $title
   *   Short header line, e.g. "multisite:create newsite.uiowa.edu".
   * @param array $summary
   *   Optional key/value rows shown before validation, assembled by the
   *   calling command. Each entry: ['label' => string, 'value' => string].
   * @param array $validation
   *   Output of runChecks().
   * @param array $steps
   *   Each entry has a 'label' key. Empty array renders no Actions block.
   */
  protected function renderPlan(
    SymfonyStyle $io,
    string $title,
    array $summary,
    array $validation,
    array $steps = [],
  ): void {
    $rule = str_repeat('─', 65);
    $io->writeln(<<<HEADER

      <options=bold>{$rule}
        {$title}
      {$rule}</>

      HEADER);

    foreach ($summary as $row) {
      $label = str_pad($row['label'] . ':', 14);
      $io->writeln("  <options=bold>{$label}</> {$row['value']}");
    }

    if ($summary) {
      $io->writeln('');
    }

    $overall = $validation['overall'];
    $color = match($overall) {
      CheckStatus::Fail => 'red',
      CheckStatus::Warn => 'yellow',
      default => 'green',
    };

    $io->writeln("  <options=bold>Validation:</> <fg={$color}>{$overall->value}</>");

    $non_pass = array_filter($validation['checks'], fn($c) => $c['status'] !== CheckStatus::Pass);
    foreach ($non_pass as $check) {
      $icon = $check['status'] === CheckStatus::Fail ? '<fg=red>✗</>' : '<fg=yellow>!</>';
      $io->writeln("    {$icon} [{$check['status']->value}] {$check['message']}");
    }

    if ($overall === CheckStatus::Fail) {
      $io->writeln('');
      return;
    }

    $io->writeln('');

    if ($steps) {
      $io->writeln('  <options=bold>Actions:</>');
      foreach ($steps as $step) {
        $io->writeln("    · {$step['label']}");
      }
      $io->writeln('');
    }
  }

  /**
   * Dispatch a decided plan: render it, then exit or apply based on mode.
   *
   * All command-specific data comes from the Plan.
   *
   * @param \Symfony\Component\Console\Style\SymfonyStyle $io
   *   The output style.
   * @param \SiteNow\Plan\Plan $plan
   *   The decided plan.
   * @param array $options
   *   Command options. Reads 'dry-run' and 'yes'.
   *
   * @return int
   *   A console exit code.
   */
  protected function executePlan(SymfonyStyle $io, Plan $plan, array $options): int {
    // Validation failed: show the failing checks (with any resolved summary,
    // e.g. the picked app) and stop before any work.
    if ($plan->failed()) {
      $this->renderPlan($io, $plan->title, $plan->summary, $plan->validation);
      return Command::FAILURE;
    }

    $this->renderPlan($io, $plan->title, $plan->summary, $plan->validation, $plan->steps());

    // Dry run: the plan was previewed; make no changes.
    if (!empty($options['dry-run'])) {
      return Command::SUCCESS;
    }

    // --yes applies without prompting, but never past a warning.
    if (!empty($options['yes'])) {
      if ($plan->warned()) {
        $io->error('Aborting: --yes was passed but validation has a WARN. Resolve the warning or run interactively.');
        return Command::FAILURE;
      }
    }
    elseif (!$io->confirm('Apply?', FALSE)) {
      $io->writeln('Aborted.');
      return Command::SUCCESS;
    }

    return $this->applyPlan($io, $plan);
  }

  /**
   * Run a plan's steps in order, failing loud with no rollback.
   *
   * A step that throws stops the run. Steps that already ran stay applied;
   * recover the working tree with git. The cloud database create is the only
   * non-git side effect, called out on abort.
   *
   * @param \Symfony\Component\Console\Style\SymfonyStyle $io
   *   The output style.
   * @param \SiteNow\Plan\Plan $plan
   *   The plan whose steps to run.
   *
   * @return int
   *   A console exit code.
   */
  protected function applyPlan(SymfonyStyle $io, Plan $plan): int {
    foreach ($plan->steps() as $step) {
      try {
        ($step['run'])($io);
      }
      catch (\Throwable $e) {
        $io->error("Step failed: {$step['label']}");
        $io->writeln($e->getMessage());
        $io->warning('No rollback was performed. Generated files remain in the working tree; recover with git. If the cloud database step had already run, the database may exist on Acquia.');
        return Command::FAILURE;
      }
    }

    $io->success('Done.');

    if ($plan->nextSteps) {
      $io->writeln('Next steps:');
      $io->listing($plan->nextSteps);
    }

    return Command::SUCCESS;
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
