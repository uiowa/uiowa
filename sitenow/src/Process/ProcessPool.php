<?php

namespace SiteNow\Process;

use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Runs argv-array commands concurrently with a bounded process pool.
 *
 * Jobs are argv arrays (e.g. ['drush', '@site.prod', 'cr']) — never shell
 * strings — so arguments pass through byte-for-byte with no escaping layer.
 * The work this pool exists for is I/O-bound (drush over SSH), so wall-clock
 * time scales down roughly linearly with the concurrency cap.
 *
 * Jobs may carry a group name (e.g. the Acquia application). No more than
 * $groupCap jobs per group run at once: SSH multiplexing (see
 * .ddev/homeadditions/.ssh/config.d) rides one connection per app server,
 * and sshd drops mux session requests past MaxSessions — 8 per app is
 * validated safe against Acquia, 16 is not.
 */
class ProcessPool {

  /**
   * Constructs the pool.
   *
   * @param int $concurrency
   *   Maximum number of simultaneous processes across all groups.
   * @param int $groupCap
   *   Maximum simultaneous jobs per group. 8 is the validated safe session
   *   count per multiplexed Acquia app connection.
   * @param int $timeout
   *   Per-job timeout in seconds.
   * @param int $retries
   *   How many times to re-run failed (non-zero exit) jobs before accepting
   *   the failure. Drupal bootstraps over SSH are occasionally flaky under
   *   concurrent load; a quieter second attempt usually succeeds.
   */
  public function __construct(
    private int $concurrency,
    private int $groupCap = 8,
    private int $timeout = 300,
    private int $retries = 1,
  ) {}

  /**
   * Run all jobs and return per-job results.
   *
   * @param array<string, array<int, string>> $jobs
   *   Map of job key => argv array.
   * @param array<string, string> $groups
   *   Optional map of job key => group name for the per-group cap.
   * @param callable|null $on_progress
   *   Optional callback invoked as (int $done, int $total, ?string $key,
   *   ?array $result) each time a job finishes ($key and $result set) and
   *   once per poll tick ($key NULL, for spinner animation), plus a final
   *   call when all jobs are done. Retry passes re-invoke it with the
   *   retried subset's counts.
   *
   * @return array<string, array{exit: int, output: string, error: string}>
   *   Map of job key => exit code, stdout, and stderr. Jobs that time out
   *   or fail to launch get a non-zero exit and the reason in 'error'.
   */
  public function run(array $jobs, array $groups = [], ?callable $on_progress = NULL): array {
    $results = $this->runPass($jobs, $this->concurrency, $groups, $on_progress);

    // Re-run failed jobs at reduced concurrency; transient bootstrap
    // failures usually succeed on a quieter second attempt.
    for ($attempt = 0; $attempt < $this->retries; $attempt++) {
      $failed = array_filter($results, fn (array $r) => $r['exit'] !== 0);
      if (empty($failed)) {
        break;
      }

      $retry_results = $this->runPass(
        array_intersect_key($jobs, $failed),
        min($this->concurrency, 4),
        $groups,
        $on_progress
      );
      $results = array_merge($results, $retry_results);
    }

    return $results;
  }

  /**
   * Run one pass of jobs through the pool.
   *
   * @param array<string, array<int, string>> $jobs
   *   Map of job key => argv array.
   * @param int $concurrency
   *   Maximum simultaneous processes for this pass.
   * @param array<string, string> $groups
   *   Map of job key => group name.
   * @param callable|null $on_progress
   *   See run().
   *
   * @return array<string, array{exit: int, output: string, error: string}>
   *   Map of job key => result.
   */
  private function runPass(array $jobs, int $concurrency, array $groups, ?callable $on_progress): array {
    $queue = array_keys($jobs);
    $total = count($jobs);
    $running = [];
    $results = [];
    $group_counts = [];

    while ($queue || $running) {

      // Top up the pool, skipping jobs whose group is at its cap.
      while ($queue && count($running) < $concurrency) {
        $key = NULL;

        foreach ($queue as $i => $candidate) {
          $group = $groups[$candidate] ?? NULL;
          if ($group === NULL || ($group_counts[$group] ?? 0) < $this->groupCap) {
            $key = $candidate;
            unset($queue[$i]);
            break;
          }
        }

        // Every queued job's group is at cap; wait for harvests.
        if ($key === NULL) {
          break;
        }

        $process = new Process($jobs[$key]);
        $process->setTimeout($this->timeout);

        try {
          $process->start();
        }
        catch (RuntimeException $e) {
          $results[$key] = [
            'exit' => 1,
            'output' => '',
            'error' => 'process failed to start: ' . $e->getMessage(),
          ];
          if ($on_progress !== NULL) {
            $on_progress(count($results), $total, $key, $results[$key]);
          }
          continue;
        }

        $running[$key] = $process;
        if (isset($groups[$key])) {
          $group_counts[$groups[$key]] = ($group_counts[$groups[$key]] ?? 0) + 1;
        }
      }

      // Harvest finished processes.
      foreach ($running as $key => $process) {
        try {
          $process->checkTimeout();
        }
        catch (ProcessTimedOutException) {
          $results[$key] = [
            'exit' => 1,
            'output' => $process->getOutput(),
            'error' => "process timed out after {$this->timeout} seconds",
          ];
          unset($running[$key]);
          if (isset($groups[$key])) {
            $group_counts[$groups[$key]]--;
          }
          if ($on_progress !== NULL) {
            $on_progress(count($results), $total, $key, $results[$key]);
          }
          continue;
        }

        if ($process->isRunning()) {
          continue;
        }

        $results[$key] = [
          'exit' => (int) $process->getExitCode(),
          'output' => $process->getOutput(),
          'error' => $process->getErrorOutput(),
        ];
        unset($running[$key]);
        if (isset($groups[$key])) {
          $group_counts[$groups[$key]]--;
        }
        if ($on_progress !== NULL) {
          $on_progress(count($results), $total, $key, $results[$key]);
        }
      }

      if ($running) {
        if ($on_progress !== NULL) {
          $on_progress(count($results), $total, NULL, NULL);
        }
        usleep(100000);
      }
    }

    if ($on_progress !== NULL) {
      $on_progress(count($results), $total, NULL, NULL);
    }

    return $results;
  }

}
