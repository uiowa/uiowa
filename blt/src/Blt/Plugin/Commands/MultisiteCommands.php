<?php

namespace Sitenow\Blt\Plugin\Commands;

use Acquia\Blt\Robo\BltTasks;
use Consolidation\AnnotatedCommand\CommandData;

/**
 * Defines commands in the "custom" namespace.
 */
class MultisiteCommands extends BltTasks {

  /**
   * This will be called before the `custom:hello` command is executed.
   *
   * @hook post-command recipes:multisite:init
   */
  public function postMultisiteInit($result, CommandData $commandData) {
    $this->say("LSDJFLSKJFLSKJFLSJKDF");
  }

}
