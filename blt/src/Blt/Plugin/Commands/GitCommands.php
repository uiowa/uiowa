<?php

namespace Sitenow\Blt\Plugin\Commands;

use Acquia\Blt\Robo\BltTasks;

/**
 * Defines commands in the Sitenow namespace.
 */
class GitCommands extends BltTasks {

  /**
   * Delete all remote branches except master and develop.
   *
   * @command clean:acquia
   */
  public function cleanAcquiaRemote() {
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
        $delete[] = $ref;
      }
    }

    $this->io()->section('Refs');
    $this->io()->listing($delete);

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

}
