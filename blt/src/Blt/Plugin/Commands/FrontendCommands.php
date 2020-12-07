<?php

namespace Uiowa\Blt\Plugin\Commands;

use Acquia\Blt\Robo\BltTasks;

/**
 * Frontend commands.
 */
class FrontendCommands extends BltTasks {

  /**
   * Install frontend dependencies based on the environment.
   *
   * @command uiowa:frontend:install
   *
   * @aliases ufi
   */
  public function install() {
    $root = $this->getConfigValue('repo.root');

    if (getenv('CI')) {
      $this->taskExecStack()
        ->dir($root)
        ->exec('npm ci')
        ->run();
    }
    else {
      $this->taskExecStack()
        ->dir($root)
        ->exec('npm install')
        ->run();
    }
  }

  /**
   * Build frontend dependencies for each workspace.
   *
   * @command uiowa:frontend:build
   *
   * @aliases ufb
   */
  public function build() {
    $root = $this->getConfigValue('repo.root');
    $config = json_decode(file_get_contents("{$root}/package.json"));

    foreach ($config->workspaces as $workspace) {
      $this->taskExecStack()
        ->dir($root)
        ->exec("npm run --prefix {$workspace} build")
        ->run();
    }
  }

}
