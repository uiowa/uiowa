<?php

namespace Sitenow\Blt\Plugin\Commands;

use Acquia\Blt\Robo\BltTasks;
use Acquia\Blt\Robo\Exceptions\BltException;

/**
 * BLT override commands.
 */
class OverrideCommands extends BltTasks {

  /**
   * Override the drupal:update BLT command.
   *
   * This override only runs updb on bootstrapped sites.
   *
   * @hook replace-command drupal:update
   */
  public function overrideDrupalUpdate() {
    $result = $this->taskDrush()
      ->stopOnFail()
      ->drush('status')
      ->option('fields', 'bootstrap')
      ->option('format', 'json')
      ->silent(TRUE)
      ->run();

    $status = json_decode($result->getMessage());

    if (empty($status->bootstrap) || $status->bootstrap != 'Successful') {
      $this->logger->warning('Site not bootstrapped. Cannot update database.');
    }
    else {
      $task = $this->taskDrush()
        ->stopOnFail()
        ->drush("updb");

      $result = $task->run();

      if (!$result->wasSuccessful()) {
        throw new BltException("Failed to execute database updates!");
      }

      $this->invokeCommands(['drupal:config:import', 'drupal:toggle:modules']);
    }
  }

  /**
   * @hook replace-command artifact:ac-hooks:post-db-copy
   */
  public function replacePostDbCopy() {

  }

}
