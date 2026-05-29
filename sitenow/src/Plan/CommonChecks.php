<?php

namespace SiteNow\Plan;

/**
 * Reusable validation checks shared by commands that use `PlanTrait`.
 *
 * Provides Check builders for the common preconditions: host-shell
 * environment, Acquia credentials, and git state. A command picks the ones
 * it needs and includes them in the list it passes to `runChecks()`.
 *
 * Requires the using class to also use `SiteNowCommandsTrait` for
 * `isHostShell()` and `getAcquiaCredentials()`.
 */
trait CommonChecks {

  /**
   * Require the command to run on the host shell.
   */
  protected function checkHostShell(): Check {
    return new Check('running_on_host_shell', function (): CheckResult {
      return $this->isHostShell()
        ? CheckResult::pass()
        : CheckResult::fail('Must run on the host shell, not inside DDEV or Acquia Cloud. Use: ./vendor/bin/robo');
    });
  }

  /**
   * Require Acquia Cloud API credentials to be configured.
   */
  protected function checkAcquiaCredentials(): Check {
    return new Check('has_acquia_credentials', function (): CheckResult {
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
      new Check('on_feature_branch', function () use ($branch, $protected): CheckResult {
        return in_array($branch, $protected)
          ? CheckResult::fail("Cannot commit on protected branch '{$branch}'.")
          : CheckResult::pass(['branch' => $branch]);
      }),
      new Check('clean_working_tree', function (): CheckResult {
        // Ignore untracked files: the command stages only its own generated
        // paths, so untracked local files cannot pollute its commit.
        $dirty = trim((string) shell_exec('git status --porcelain --untracked-files=no 2>/dev/null'));
        return $dirty
          ? CheckResult::fail('Working tree has uncommitted changes to tracked files.')
          : CheckResult::pass();
      }),
      new Check('up_to_date_with_origin', function () use ($branch): CheckResult {
        shell_exec('git fetch origin --quiet 2>/dev/null');
        $rev = trim((string) shell_exec("git rev-list --left-right --count origin/{$branch}...HEAD 2>/dev/null"));
        $parts = explode("\t", $rev);
        $behind = (int) ($parts[0] ?? 0);
        return $behind > 0
          ? CheckResult::fail("Branch is {$behind} commit(s) behind origin/{$branch}.")
          : CheckResult::pass();
      }),
    ];
  }

}
