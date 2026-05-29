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
   * Constructs a Check.
   *
   * @param string $name
   *   Machine name recorded in the validation results.
   * @param \Closure $run
   *   Returns a CheckResult when invoked: function (): CheckResult.
   */
  public function __construct(
    public readonly string $name,
    public readonly \Closure $run,
  ) {}

  /**
   * Evaluate the check.
   *
   * @return \SiteNow\Plan\CheckResult
   *   The check result.
   */
  public function evaluate(): CheckResult {
    return ($this->run)();
  }

}
