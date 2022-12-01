<?php

namespace Uiowa\Blt\Plugin\Commands;

use Acquia\Blt\Robo\BltTasks;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\CommandError;

/**
 * This class should contain hooks that are used in other commands.
 */
class ValidateCommands extends BltTasks {

  /**
   * Validate that the command is being run on the container.
   *
   * @hook validate @requireContainer
   */
  public function validateContainer() {
    if (!$this->isDdev()) {
      return new CommandError('This command must be run on the web container, i.e. either with ddev ... or after running ddev ssh.');
    }
  }

  /**
   * Validate that the command is not being run on the container.
   *
   * @hook validate @requireHost
   */
  public function validateHost() {
    if ($this->isDdev()) {
      return new CommandError('This command must be run on your host machine, i.e. not on the ddev web container.');
    }
  }

  /**
   * Validate that the command is being run on a feature branch.
   *
   * @hook validate @requireFeatureBranch
   */
  public function validateFeatureBranch() {
    $result = $this->taskGit()
      ->dir($this->getConfigValue('repo.root'))
      ->exec('git rev-parse --abbrev-ref HEAD')
      ->interactive(FALSE)
      ->printOutput(FALSE)
      ->printMetadata(FALSE)
      ->run();

    $branch = $result->getMessage();

    if ($branch == 'main' || $branch == 'develop') {
      return new CommandError('You must run this command on a feature branch created from the default branch.');
    }
  }

  /**
   * Validate necessary credentials are set.
   *
   * @hook validate @requireCredentials
   */
  public function validateCredentials() {
    $credentials = [
      'uiowa.credentials.acquia.key',
      'uiowa.credentials.acquia.secret',
    ];

    foreach ($credentials as $cred) {
      if (!$this->getConfigValue($cred)) {
        return new CommandError("You must set {$cred} in your {$this->getConfigValue('repo.root')}/blt/local.blt.yml file. DO NOT commit these anywhere in the repository!");
      }
    }
  }

  /**
   * Validate Git remote access.
   *
   * @hook validate @requireRemoteAccess
   */
  public function validateRemoteAccess(CommandData $commandData) {
    $remotes = $this->getConfigValue('git.remotes');

    foreach ($remotes as $remote) {
      $result = $this->taskExecStack()
        ->exec("git ls-remote {$remote}")
        ->stopOnFail()
        ->silent(TRUE)
        ->run();

      if (!$result->wasSuccessful()) {
        return new CommandError("Error connecting to Acquia remote {$remote}. Double check permissions and SSH key.");
      }
    }
  }

  /**
   * Determine if command is running on ddev container.
   *
   * @return bool
   *   Is ddev or not.
   */
  protected function isDdev() {
    return getenv('IS_DDEV_PROJECT') ?? FALSE;
  }

}
