<?php

namespace Sitenow\Blt\Plugin\Commands;

use Acquia\Blt\Robo\BltTasks;
use Acquia\Blt\Robo\Common\YamlMunge;
use Acquia\Blt\Robo\Exceptions\BltException;

/**
 * BLT override commands.
 */
class ReplaceCommands extends BltTasks {

  /**
   * Replace the drupal:update BLT command.
   *
   * Only run updb on bootstrapped sites.
   *
   * @hook replace-command drupal:update
   */
  public function replaceDrupalUpdate() {
    $result = $this->taskDrush()
      ->stopOnFail()
      ->drush('status')
      ->option('fields', 'bootstrap')
      ->option('format', 'json')
      ->silent(TRUE)
      ->run();

    $status = json_decode($result->getMessage());

    if (empty($status->bootstrap) || $status->bootstrap != 'Successful') {
      $result = $this->taskDrush()
        ->stopOnFail()
        ->drush('sql:query')
        ->arg('show tables')
        ->silent(TRUE)
        ->run();

      $tables = $result->getMessage();

      if (empty($tables)) {
        $this->logger->warning('No database tables found. Installing sitenow....');
        $uri = $this->getConfig()->get('site');

        $uid = uniqid('admin_');

        $this->taskDrush()
          ->stopOnFail()
          ->drush('site:install')
          ->arg('sitenow')
          ->options([
            'sites-subdir' => $uri,
            'existing-config' => NULL,
            'account-name' => $uid,
            'account-mail' => base64_decode('aXRzLXdlYkB1aW93YS5lZHU='),
          ])
          ->run();

        // Post-install tasks.
        // @see: https://www.drupal.org/project/drupal/issues/2982052
        $this->taskDrush()
          ->drush('user:role:add')
          ->args([
            'administrator',
            $uid,
          ])
          ->run();

        $this->taskDrush()
          ->drush('config:set')
          ->args([
            'system.site',
            'name',
            $uri,
          ])
          ->run();
      }
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
   * Replace the post-db-copy AC hook.
   *
   * @hook replace-command artifact:ac-hooks:post-db-copy
   */
  public function replacePostDbCopy($site, $target_env, $db_name, $source_env) {
    $root = $this->getConfigValue('repo.root');

    foreach ($this->getConfigValue('multisites') as $multisite) {
      // Parse each multisite blt.yml file to get the correct database.
      $yaml = YamlMunge::parseFile("{$root}/docroot/sites/{$multisite}/blt.yml");

      // Trigger drupal:update for this site.
      if ($db_name == $yaml['drupal']['db']['database']) {
        $this->say("Deploying updates to <comment>{$multisite}</comment>...");
        $this->switchSiteContext($multisite);
        $this->taskDrush()->drush('cache:rebuild')->run();
        $this->invokeCommand('drupal:update');
        $this->say("Finished deploying updates to {$multisite}.");

        break;
      }
    }
  }

}
