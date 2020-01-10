<?php

namespace Uiowa\Blt\Plugin\Commands\Sitenow;

use Acquia\Blt\Robo\BltTasks;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\CommandError;

/**
 * Defines commands in the Sitenow namespace.
 */
class GitCommands extends BltTasks {

  /**
   * Validate clean command.
   *
   * @hook validate sitenow:git:clean
   */
  public function validateClean(CommandData $commandData) {
    $result = $this->taskExecStack()
      ->exec('git remote')
      ->stopOnFail()
      ->silent(TRUE)
      ->run();

    $output = $result->getMessage();
    $remotes = explode(PHP_EOL, $output);

    if (!in_array('acquia', $remotes)) {
      return new CommandError('You must add a remote named acquia pointing to the Acquia Git repository. Check the README for details.');
    }

    $result = $this->taskExecStack()
      ->exec('git ls-remote acquia')
      ->stopOnFail()
      ->run();

    if (!$result->wasSuccessful()) {
      return new CommandError('Error connecting to acquia remote. Double check permissions and SSH key.');
    }
  }

  /**
   * Delete all remote branches except master and develop from Acquia remote.
   *
   * @command sitenow:git:clean
   *
   * @aliases sgc
   */
  public function clean() {
    $result = $this->taskExecStack()
      ->exec('git ls-remote --heads acquia')
      ->stopOnFail()
      ->silent(TRUE)
      ->run();

    $output = $result->getMessage();
    $heads = explode(PHP_EOL, $output);

    $keep = [
      'refs/heads/master',
      'refs/heads/pipelines-build-master',
      'refs/heads/pipelines-build-develop',
    ];

    $delete = [];

    foreach ($heads as $head) {
      list($sha, $ref) = explode("\t", $head);

      if (!in_array($ref, $keep)) {
        $delete[$sha] = $ref;
      }
    }

    if (!empty($delete)) {
      $this->printArrayAsTable($delete, ['SHA', 'Ref']);

      if (!$this->confirm('You will delete all the branches above from the Acquia remote. Are you sure?')) {
        throw new \Exception('Aborted.');
      }
      else {
        foreach ($delete as $ref) {
          $this->taskExecStack()
            ->exec("git push --delete acquia {$ref}")
            ->run();
        }
      }
    }
    else {
      $this->yell('There are no branches to clean up!');
    }
  }

  /**
   * Write the branch SHA or tag to the profile info file.
   *
   * @hook post-command artifact:build
   */
  public function writeProfileVersion() {
    $event = getenv('PIPELINE_WEBHOOK_EVENT');
    $path = getenv('PIPELINE_VCS_PATH');
    $sha = getenv('PIPELINE_GIT_HEAD_REF');

    if ($event == 'TAG_PUSH') {
      $version = $path;
    }
    elseif ($event == 'BRANCH_PUSH' || $event == 'PULL_REQUEST') {
      $version = "{$path}-{$sha}";
    }

    $file = $this->getConfigValue('deploy.dir') . '/docroot/profiles/custom/sitenow/sitenow.info.yml';

    if (isset($version)) {
      $data = "version: '{$version}'";
      file_put_contents($file, $data, FILE_APPEND);
      $this->logger->info("Appended Git version {$version} to {$file}.");
    }
    else {
      $this->logger->warning("Unable to append Git version to {$file}.");
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
