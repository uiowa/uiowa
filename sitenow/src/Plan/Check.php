<?php

namespace SiteNow\Plan;

/**
 * A named validation check.
 *
 * Pairs a check name with the logic (a closure) that produces its result. A
 * command assembles a list of checks and hands it to `PlanTrait::runChecks()`,
 * which evaluates each one and collects the results keyed by name.
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
