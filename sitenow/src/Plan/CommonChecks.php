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
    return new Check('running_on_host_shell', function (): Precondition {
      return $this->isHostShell()
        ? Precondition::pass('running_on_host_shell')
        : Precondition::fail('running_on_host_shell', 'Must run on the host shell, not inside DDEV or Acquia Cloud. Use: ./vendor/bin/robo');
    });
  }

  /**
   * Require Acquia Cloud API credentials to be configured.
   */
  protected function checkAcquiaCredentials(): Check {
    return new Check('has_acquia_credentials', function (): Precondition {
      $creds = $this->getAcquiaCredentials();
      return (!empty($creds['key']) && !empty($creds['secret']))
        ? Precondition::pass('has_acquia_credentials')
        : Precondition::fail('has_acquia_credentials', 'Acquia credentials not found. Set uiowa.credentials.acquia.key/secret in blt/local.blt.yml.');
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
      new Check('on_feature_branch', function () use ($branch, $protected): Precondition {
        return in_array($branch, $protected)
          ? Precondition::fail('on_feature_branch', "Cannot commit on protected branch '{$branch}'.")
          : Precondition::pass('on_feature_branch', ['branch' => $branch]);
      }),
      new Check('clean_working_tree', function (): Precondition {
        // Ignore untracked files: the command stages only its own generated
        // paths, so untracked local files cannot pollute its commit.
        $dirty = trim((string) shell_exec('git status --porcelain --untracked-files=no 2>/dev/null'));
        return $dirty
          ? Precondition::fail('clean_working_tree', 'Working tree has uncommitted changes to tracked files.')
          : Precondition::pass('clean_working_tree');
      }),
      new Check('up_to_date_with_origin', function () use ($branch): Precondition {
        shell_exec('git fetch origin --quiet 2>/dev/null');
        $rev = trim((string) shell_exec("git rev-list --left-right --count origin/{$branch}...HEAD 2>/dev/null"));
        $parts = explode("\t", $rev);
        $behind = (int) ($parts[0] ?? 0);
        return $behind > 0
          ? Precondition::fail('up_to_date_with_origin', "Branch is {$behind} commit(s) behind origin/{$branch}.")
          : Precondition::pass('up_to_date_with_origin');
      }),
    ];
  }

}
