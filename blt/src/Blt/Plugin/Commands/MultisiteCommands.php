<?php

namespace Uiowa\Blt\Plugin\Commands;

use Acquia\Blt\Robo\BltTasks;

/**
 * Global multisite commands.
 */
class MultisiteCommands extends BltTasks {

  /**
   * A no-op command.
   *
   * This is called in sync.commands to override the frontend step.
   *
   * Compiling frontend assets on a per-site basis is not necessary since we
   * use Yarn workspaces for that. This allows for faster syncs.
   *
   * @see: https://github.com/acquia/blt/issues/3697
   *
   * @command uiowa:multisite:noop
   *
   * @aliases umn
   */
  public function noop() {

  }

  /**
   * Execute a Drush command against all multisites.
   *
   * @param string $cmd
   *   The simple Drush command to execute, e.g. 'cron' or 'cache:rebuild'. No
   *    support for options or arguments at this time.
   *
   * @command uiowa:multisite:execute
   *
   * @aliases sme
   *
   * @throws \Exception
   */
  public function execute($cmd) {
    if (!$this->confirm("You will execute 'drush {$cmd}' on all multisites. Are you sure?", TRUE)) {
      throw new \Exception('Aborted.');
    }
    else {
      foreach ($this->getConfigValue('multisites') as $multisite) {
        $this->switchSiteContext($multisite);

        $this->taskDrush()
          ->drush($cmd)
          ->run();
      }
    }
  }

}
