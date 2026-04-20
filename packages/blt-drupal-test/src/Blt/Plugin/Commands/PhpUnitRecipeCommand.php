<?php

namespace Acquia\BltDrupalTest\Blt\Plugin\Commands;

use Acquia\Blt\Robo\BltTasks;
use Acquia\Blt\Robo\Exceptions\BltException;
use Robo\Contract\VerbosityThresholdInterface;

/**
 * Defines commands in the "recipes:phpunit:*" namespace.
 */
class PhpUnitRecipeCommand extends BltTasks {

  /**
   * Generates example files for writing PHPUnit tests.
   *
   * @command recipes:phpunit:init
   *
   * @throws \Acquia\Blt\Robo\Exceptions\BltException
   */
  public function init() {
    $result = $this->taskFilesystemStack()
      ->copy(
        $this->getConfigValue('repo.root') . '/vendor/acquia/blt-drupal-test/scripts/ExampleTest.php',
        $this->getConfigValue('repo.root') . '/tests/phpunit/ExampleTest.php', FALSE)
      ->stopOnFail()
      ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
      ->run();

    if (!$result->wasSuccessful()) {
      throw new BltException("Could not copy example files into the repository root.");
    }

    $this->say("<info>Example PHPUnit files were copied to your application.</info>");
  }

}
