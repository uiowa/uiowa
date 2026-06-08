<?php

namespace SiteNow\Plan;

/**
 * Reusable checks shared by commands that use `PlanTrait`.
 *
 * A command picks the builders it needs and includes them in the list it
 * passes to `runChecks()`.
 *
 * Requires the using class to also use `SiteNowCommandsTrait` for
 * `isHostShell()` and `getAcquiaCredentials()`.
 */
trait CommonChecks {

  // Machine names recorded in validation results.
  const CHECK_HOST_SHELL = 'running_on_host_shell';
  const CHECK_ACQUIA_CREDENTIALS = 'has_acquia_credentials';
  const CHECK_ON_FEATURE_BRANCH = 'on_feature_branch';
  const CHECK_CLEAN_WORKING_TREE = 'clean_working_tree';
  const CHECK_UP_TO_DATE_WITH_ORIGIN = 'up_to_date_with_origin';

  /**
   * Require the command to run on the host shell.
   */
  protected function checkHostShell(): Check {
    return new Check(self::CHECK_HOST_SHELL, function (): CheckResult {
      return $this->isHostShell()
        ? CheckResult::pass()
        : CheckResult::fail('Must run on the host shell, not inside DDEV or Acquia Cloud. Use: ./sitenow/bin/sitenow');
    });
  }

  /**
   * Require Acquia Cloud API credentials to be configured.
   */
  protected function checkAcquiaCredentials(): Check {
    return new Check(self::CHECK_ACQUIA_CREDENTIALS, function (): CheckResult {
      $creds = $this->getAcquiaCredentials();
      return (!empty($creds['key']) && !empty($creds['secret']))
        ? CheckResult::pass()
        : CheckResult::fail('Acquia credentials not found. Set uiowa.credentials.acquia.key/secret in blt/local.blt.yml.');
    });
  }

  /**
   * Git state checks required before committing.
   *
   * @param string $branch
   *   The current branch name.
   *
   * @return \SiteNow\Plan\Check[]
   *   Checks for branch, working tree cleanliness, and origin sync.
   */
  protected function gitChecks(string $branch): array {
    $protected = ['main', 'master', 'develop'];

    return [
      new Check(self::CHECK_ON_FEATURE_BRANCH, function () use ($branch, $protected): CheckResult {
        return in_array($branch, $protected)
          ? CheckResult::fail("Cannot commit on protected branch '{$branch}'.")
          : CheckResult::pass(['branch' => $branch]);
      }),
      new Check(self::CHECK_CLEAN_WORKING_TREE, function (): CheckResult {
        // Ignore untracked files: the command stages only its own generated
        // paths, so untracked local files cannot pollute its commit.
        $dirty = trim((string) shell_exec('git status --porcelain --untracked-files=no 2>/dev/null'));
        return $dirty
          ? CheckResult::fail('Working tree has uncommitted changes to tracked files.')
          : CheckResult::pass();
      }),
      new Check(self::CHECK_UP_TO_DATE_WITH_ORIGIN, function () use ($branch): CheckResult {
        shell_exec('git fetch origin --quiet 2>/dev/null');
        $range = escapeshellarg("origin/{$branch}...HEAD");
        $rev = trim((string) shell_exec("git rev-list --left-right --count {$range} 2>/dev/null"));
        $parts = explode("\t", $rev);
        $behind = (int) ($parts[0] ?? 0);
        return $behind > 0
          ? CheckResult::fail("Branch is {$behind} commit(s) behind origin/{$branch}.")
          : CheckResult::pass();
      }),
    ];
  }

}
