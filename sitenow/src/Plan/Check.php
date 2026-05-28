<?php

namespace SiteNow\Plan;

/**
 * A named, deferred validation check.
 *
 * Used by `PlanTrait::runChecks()`. Deferring evaluation lets the runner gate
 * expensive checks (Acquia API calls, git operations) behind cheaper ones
 * (input validation).
 */
class Check {

  /**
   * @param string $name
   *   Machine name recorded in the validation results.
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
   * @return \SiteNow\Plan\Precondition
   *   The check result.
   */
  public function evaluate(): Precondition {
    return ($this->run)();
  }

}
