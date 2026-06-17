<?php

namespace SiteNow\Report;

use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Runs drush against a site alias and returns its raw result.
 *
 * Uses Symfony Process with array arguments — no shell interpolation — and
 * keeps stdout and stderr separate so callers parse command output without
 * Acquia connection chatter mixed in.
 */
class DrushRunner {

  /**
   * Constructs the runner.
   *
   * @param int $timeout
   *   Per-call timeout in seconds. Drush over SSH can be slow on a cold
   *   connection, so this is higher than Process's 60s default.
   */
  public function __construct(
    private int $timeout = 120,
  ) {}

  /**
   * Run a drush command against a site alias.
   *
   * @param string $alias
   *   The drush alias without the leading '@' (e.g. 'siteid.prod').
   * @param array $args
   *   Drush arguments, each a separate element (e.g. ['php:eval', $php]).
   *
   * @return array{exit: int, output: string, error: string}
   *   The exit code, stdout, and stderr.
   */
  public function run(string $alias, array $args): array {
    $process = new Process(array_merge(['drush', "@{$alias}"], $args));
    $process->setTimeout($this->timeout);

    // A timeout or a launch failure throws rather than returning a non-zero
    // exit. Convert both to the non-zero-exit result callers already handle
    // gracefully, so one unresponsive site can't crash the whole run.
    try {
      $process->run();
    }
    catch (ProcessTimedOutException) {
      return ['exit' => 1, 'output' => '', 'error' => "drush timed out after {$this->timeout}s"];
    }
    catch (RuntimeException $e) {
      return ['exit' => 1, 'output' => '', 'error' => 'drush failed to start: ' . $e->getMessage()];
    }

    return [
      'exit' => (int) $process->getExitCode(),
      'output' => $process->getOutput(),
      'error' => $process->getErrorOutput(),
    ];
  }

}
