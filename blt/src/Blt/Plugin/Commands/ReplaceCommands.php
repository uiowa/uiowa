<?php

namespace Uiowa\Blt\Plugin\Commands;

use Acquia\Blt\Robo\BltTasks;
use Acquia\Blt\Robo\Common\EnvironmentDetector;
use Acquia\Blt\Robo\Exceptions\BltException;

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

    $app = EnvironmentDetector::getAhGroup() ? EnvironmentDetector::getAhGroup() : 'local';
    $multisite_exception = FALSE;

    foreach ($this->getConfigValue('multisites') as $multisite) {
      $this->switchSiteContext($multisite);
      $db = $this->getConfigValue('drupal.db.database');

      // Check for database include on this application.
      if (EnvironmentDetector::isAhEnv() && !file_exists("/var/www/site-php/{$app}/{$db}-settings.inc")) {
        $this->say("Skipping {$multisite} on AH environment. Database {$db} does not exist.");
        continue;
      }
      else {
        if ($this->getInspector()->isDrupalInstalled()) {
          $this->say("Deploying updates to <comment>{$multisite}</comment>...");

          try {
            $this->invokeCommand('drupal:update');
            $this->say("Finished deploying updates to {$multisite}.");
          }
          catch (BltException $e) {
            $this->logger->error("Failed deploying updates to {$multisite}.");
            $multisite_exception = TRUE;
          }
        }
      }
    }

    // If a multisite encountered a handled exception, throw one here so that
    // we return 1 and mark the job as a failure.
    if ($multisite_exception) {
      throw new \Exception('Error deploying updates. Check the log output for more information.');
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

}
