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

    // Unshift uiowa.edu to the beginning so it runs first.
    $multisites = $this->getConfigValue('multisites');

    if ($key = array_search('uiowa.edu', $multisites)) {
      unset($multisites[$key]);
      array_unshift($multisites, 'uiowa.edu');
    }

    foreach ($multisites as $multisite) {
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

          // Invalidate the Twig cache if on AH env. This happens automatically
          // for the default site but not multisites. We don't need to pass
          // the multisite URI here since we switch site context above.
          // @see: https://support.acquia.com/hc/en-us/articles/360005167754-Drupal-8-Twig-cache
          $script = '/var/www/site-scripts/invalidate-twig-cache.php';

          if (file_exists($script)) {
            $this->taskDrush()
              ->drush('php:script')
              ->arg($script)
              ->run();
          }

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

  /**
   * Roll our own version of blt/drupal-check that scans multisite code.
   *
   * @command tests:deprecated
   */
  public function testsDeprecated() {
    $this->say("Checking for deprecated code.");
    $bin = $this->getConfigValue('composer.bin');
    $root = $this->getConfigValue('repo.root');
    $docroot = $this->getConfigValue('docroot');

    $paths = [
      "{$root}/tests/" => '',
      "{$docroot}/profiles/custom/" => '',
      "{$docroot}/modules/custom/" => '',
      "{$docroot}/themes/custom/" => '',
      "{$docroot}/sites/" => "{$docroot}/sites/simpletest",
    ];

    foreach ($paths as $path => $exclude) {
      if (!empty($exclude)) {
        $cmd = "$bin/drupal-check -e {$exclude} -d {$path}";
      }
      else {
        $cmd = "$bin/drupal-check -d {$path}";
      }

      $result = $this->taskExecStack()
        ->dir($this->getConfigValue('repo.root'))
        ->exec($cmd)
        ->run();

      $exit_code = $result->getExitCode();

      if ($exit_code) {
        $this->logger->notice('Review deprecation warnings and re-run.');
        throw new BltException("Drupal Check in {$path} failed.");
      }
    }
  }

}
