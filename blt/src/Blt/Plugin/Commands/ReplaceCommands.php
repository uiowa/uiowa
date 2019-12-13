<?php

namespace Sitenow\Blt\Plugin\Commands;

use Acquia\Blt\Robo\BltTasks;
use Acquia\Blt\Robo\Common\YamlMunge;

/**
 * BLT override commands.
 */
class ReplaceCommands extends BltTasks {

  /**
   * Replace the artifact:update:drupal:all-sites BLT command.
   *
   * Run a site:install if Drupal is not installed.
   *
   * @hook replace-command artifact:update:drupal:all-sites
   */
  public function replaceDrupalUpdateAll() {
    // Disable alias since we are targeting specific uri.
    $this->config->set('drush.alias', '');

    foreach ($this->getConfigValue('multisites') as $multisite) {
      $this->switchSiteContext($multisite);

      if ($this->getInspector()->isDrupalInstalled()) {
        $this->say("Deploying updates to <comment>$multisite</comment>...");
        $this->invokeCommand('drupal:update');
        $this->say("Finished deploying updates to $multisite.");
      }
      else {
        $this->logger->warning("Drupal not installed for <comment>$multisite</comment>. Installing from configuration in sitenow profile....");
        $uri = $this->getConfig()->get('site');

        if (empty($uri)) {
          throw new \Exception('Cannot determine site directory for installation.');
        }

        $uid = uniqid('admin_');

        $result = $this->taskDrush()
          ->stopOnFail(TRUE)
          ->drush('site:install')
          ->arg('sitenow')
          ->options([
            'sites-subdir' => $uri,
            'existing-config' => NULL,
            'account-name' => $uid,
            'account-mail' => base64_decode('aXRzLXdlYkB1aW93YS5lZHU='),
          ])
          ->drush('user:role:add')
          ->args([
            'administrator',
            $uid,
          ])
          ->drush('config:set')
          ->args([
            'system.site',
            'name',
            $uri,
          ])
          ->run();

        if (!$result->wasSuccessful()) {
          throw new \Exception("Site install task failed for {$uri}.");
        }

        $root = $this->getConfigValue('repo.root');
        $yaml = YamlMunge::parseFile("{$root}/docroot/sites/{$multisite}/blt.yml");

        if (isset($yaml['project'], $yaml['project']['requester']) && !empty($yaml['project']['requester'])) {
          $result = $this->taskDrush()
            ->stopOnFail(FALSE)
            ->drush('user:create')
            ->args([$yaml['project']['requester']])
            ->drush('user:role:add')
            ->args([
              'webmaster',
              $yaml['project']['requester'],
            ])
            ->run();

          if (!$result->wasSuccessful()) {
            throw new \Exception("Webmaster task failed for {$uri}.");
          }
        }
      }
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
