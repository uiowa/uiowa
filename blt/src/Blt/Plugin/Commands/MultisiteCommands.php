<?php


namespace Uiowa\Blt\Plugin\Commands;


use Acquia\Blt\Robo\BltTasks;

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
}
