<?php

namespace SiteNow\Robo\Plan;

/**
 * A declared pre-flight check.
 *
 * A check is lazy: its closure runs only when the validation runner evaluates
 * it. Declaring checks rather than evaluating them inline lets a command gate
 * expensive work, such as API calls, behind a cheaper batch.
 */
final class Check {

  /**
   * @param string $name
   *   Machine name recorded in the validation block.
   * @param \Closure $run
   *   Returns a Precondition when invoked: function (): Precondition.
   */
  public function __construct(
    public readonly string $name,
    public readonly \Closure $run,
  ) {}

  /**
   * Evaluate the check.
   *
   * @return \SiteNow\Robo\Plan\Precondition
   *   The check result.
   */
  public function evaluate(): Precondition {
    return ($this->run)();
  }

}
