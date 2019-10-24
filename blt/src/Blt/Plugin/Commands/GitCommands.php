<?php

namespace Sitenow\Blt\Plugin\Commands;

use Acquia\Blt\Robo\BltTasks;

/**
 * Defines commands in the Sitenow namespace.
 */
class GitCommands extends BltTasks {

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
    elseif ($event == 'BRANCH_PUSH') {
      $version = "{$path}-{$sha}";
    }

    if (isset($version)) {
      $file = $this->getConfigValue('deploy.dir') . '/docroot/profiles/custom/sitenow/sitenow.info.yml';
      $data = "\nversion: '{$version}'";
      file_put_contents($file, $data, FILE_APPEND);
      $this->logger->info("Appended Git version {$version} to {$file}.");
    }
  }

}
