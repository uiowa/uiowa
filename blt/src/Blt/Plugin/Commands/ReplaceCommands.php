<?php

namespace Uiowa\Blt\Plugin\Commands;

use Acquia\Blt\Robo\BltTasks;
use Acquia\Blt\Robo\Common\EnvironmentDetector;

/**
 * BLT override commands.
 */
class ReplaceCommands extends BltTasks {

  /**
   * Replace the artifact:update:drupal:all-sites BLT command.
   *
   * @hook replace-command artifact:update:drupal:all-sites
   */
  public function replaceDrupalUpdateAll() {
    // Disable alias since we are targeting a specific URI.
    $this->config->set('drush.alias', '');

    $app = EnvironmentDetector::getAhGroup() ?? 'local';

    foreach ($this->getConfigValue('multisites') as $multisite) {
      $this->switchSiteContext($multisite);
      $db = $this->getConfigValue('drupal.db.database');

      // Check for database include on this application.
      if (EnvironmentDetector::isAhEnv() && !file_exists("/var/www/site-php/{$app}/{$db}-settings.inc")) {
        $this->say("Skipping $multisite on AH environment. Database {$db} does not exist.");
        continue;
      }
      else {
        if ($this->getInspector()->isDrupalInstalled()) {
          $this->say("Deploying updates to <comment>$multisite</comment>...");
          $this->invokeCommand('drupal:update');
          $this->say("Finished deploying updates to $multisite.");
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
    foreach ($this->getConfigValue('multisites') as $multisite) {
      $this->switchSiteContext($multisite);

      $db = $this->getConfigValue('drupal.db.database');

      // Trigger drupal:update for this site.
      if ($db_name == $db) {
        $this->say("Deploying updates to <comment>{$multisite}</comment>...");
        $this->switchSiteContext($multisite);
        $this->taskDrush()->drush('cache:rebuild')->run();
        $this->invokeCommand('drupal:update');
        $this->say("Finished deploying updates to {$multisite}.");

        break;
      }
    }
  }

  /**
   * Replace blt setup command.
   *
   * This allows CI to test multiple install profiles using the site specified.
   *
   * @hook replace-command setup
   */
  public function replaceSetup() {
    if (EnvironmentDetector::isCiEnv()) {
      $this->invokeCommands([
        'source:build',
        'drupal:deployment-identifier:init',
      ]);

      foreach ($this->getConfigValue('uiowa.profiles') as $profile => $data) {
        $this->say("Installing {$profile} profile on site <comment>{$data['ci_site']}</comment>.");

        // Disable alias since we are targeting a specific URI.
        $this->config->set('drush.alias', '');

        $this->switchSiteContext($data['ci_site']);
        $this->invokeCommand('drupal:install');
      }

      $this->invokeCommand('blt:init:shell-alias');
    }
    else {
      $this->say("Setting up local environment for site <comment>{$this->getConfigValue('site')}</comment>.");
      if ($this->getConfigValue('drush.alias')) {
        $this->say("Using drush alias <comment>@{$this->getConfigValue('drush.alias')}</comment>");
      }

      $this->invokeCommands([
        'source:build',
        'drupal:deployment-identifier:init',
        'drupal:install',
        'drupal:toggle:modules',
      ]);
    }
  }

}
