<?php

namespace Uiowa\Blt\Plugin\Commands;

use Acquia\Blt\Robo\BltTasks;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\CommandError;

/**
 * Git commands.
 */
class GitCommands extends BltTasks {

  /**
   * Validate clean command.
   *
   * @hook validate uiowa:git:clean
   */
  public function validateClean(CommandData $commandData) {
    $remotes = $this->getConfigValue('git.remotes');

    foreach ($remotes as $remote) {
      $result = $this->taskExecStack()
        ->exec("git ls-remote {$remote}")
        ->stopOnFail()
        ->run();

      if (!$result->wasSuccessful()) {
        return new CommandError("Error connecting to Acquia remote {$remote}. Double check permissions and SSH key.");
      }
    }
  }

  /**
   * Delete all artifact branches except master/develop from Acquia remotes.
   *
   * @command uiowa:git:clean
   *
   * @aliases ugc
   */
  public function clean() {
    $remotes = $this->getConfigValue('git.remotes');

    $keep = [
      'refs/heads/master',
      'refs/heads/pipelines-build-master',
      'refs/heads/pipelines-build-develop',
    ];

    $delete = [];

    foreach ($remotes as $remote) {
      $delete[$remote] = [];

      $result = $this->taskExecStack()
        ->exec("git ls-remote --heads {$remote}")
        ->stopOnFail()
        ->silent(TRUE)
        ->run();

      $output = $result->getMessage();
      $heads = explode(PHP_EOL, $output);

      foreach ($heads as $head) {
        list($sha, $ref) = explode("\t", $head);
        $sha = substr($sha, 0, 8);

        if (!in_array($ref, $keep)) {
          $delete[$remote][$sha] = $ref;
        }
      }

      $this->printArrayAsTable($delete[$remote], ['SHA', 'Ref']);
    }

    if (!empty($delete)) {
      if (!$this->confirm('You will delete the branches in the tables above from the Acquia remotes. Are you sure?')) {
        throw new \Exception('Aborted.');
      }
      else {
        foreach ($delete as $remote => $data) {
          foreach ($data as $sha => $ref) {
            $this->taskExecStack()
              ->exec("git push --delete {$remote} {$ref}")
              ->run();
          }
        }
      }
    }
    else {
      $this->yell('There are no branches to clean up!');
    }
  }

  /**
   * Write the branch SHA or tag to the profile info files.
   *
   * @hook post-command artifact:build
   */
  public function writeProfileVersion() {
    $tag = getenv('TRAVIS_TAG');
    $sha = getenv('TRAVIS_COMMIT');

    if (!empty($tag)) {
      $version = $tag;
    }
    else {
      $version = $sha;
    }

    $profiles = array_keys($this->getConfigValue('uiowa.profiles'));

    foreach ($profiles as $profile) {
      $file = $this->getConfigValue('deploy.dir') . "/docroot/profiles/custom/{$profile}/{$profile}.info.yml";

      if (isset($version)) {
        $data = "version: '{$version}'";
        file_put_contents($file, $data, FILE_APPEND);
        $this->logger->info("Appended Git version {$version} to {$file}.");
      }
      else {
        $this->logger->warning("Unable to append Git version to {$file}.");
      }
    }
  }

  /**
   * Copy SiteNow Drush commands into the build artifact before it is committed.
   *
   * Since drush/Commands/ is listed in the upstream deploy-exclude.txt file,
   * any hard-coded commands (ex. PolicyCommands.php) will not be committed to
   * the build artifact. Rather than override that file and lose upstream
   * changes, we can copy our Drush commands via a post-command hook.
   *
   * @hook post-command artifact:build
   */
  public function copyDrushCommands() {
    $root = $this->getConfigValue('repo.root');
    $deploy_dir = $this->getConfigValue('deploy.dir');

    $this->taskFilesystemStack()
      ->stopOnFail()
      ->copy("{$root}/drush/Commands/SitenowCommands.php", "{$deploy_dir}/drush/Commands/SitenowCommands.php")
      ->run();

    $this->logger->info('Copied SiteNow Drush commands to deploy directory.');
  }

}
