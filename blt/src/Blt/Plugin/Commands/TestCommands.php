<?php

namespace Sitenow\Blt\Plugin\Commands;

use Acquia\Blt\Robo\BltTasks;
use Consolidation\AnnotatedCommand\CommandData;

/**
 * Workaround for https://github.com/acquia/blt/issues/3611.
 */
class TestCommands extends BltTasks {

  /**
   * Start the webserver before running PHPUnit tests.
   *
   * @hook pre-command tests:phpunit:run
   */
  public function prePhpUnitTestRun(CommandData $commandData) {
    if ($this->getConfigValue('tests.run-server')) {
      $this->invokeCommand('tests:server:start');
    }
  }

  /**
   * Start the webserver before running PHPUnit tests.
   *
   * @hook post-command tests:phpunit:run
   */
  public function postPhpUnitTestRun($result, CommandData $commandData) {
    if ($this->getConfigValue('tests.run-server')) {
      $this->invokeCommand('tests:server:kill');
    }
  }

}
