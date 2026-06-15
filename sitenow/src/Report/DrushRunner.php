<?php

namespace SiteNow\Report;

use Symfony\Component\Process\Process;

/**
 * Runs drush against a site alias and returns its raw result.
 *
 * Uses Symfony Process with array arguments (no shell interpolation) instead
 * of the report commands' former exec()/shell_exec() shell strings. stdout and
 * stderr are kept separate so callers parse command output without Acquia
 * connection chatter mixed in.
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
    $process->run();

    return [
      'exit' => (int) $process->getExitCode(),
      'output' => $process->getOutput(),
      'error' => $process->getErrorOutput(),
    ];
  }

}
