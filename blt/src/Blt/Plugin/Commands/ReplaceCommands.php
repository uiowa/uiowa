<?php

namespace Uiowa\Blt\Plugin\Commands;

use Acquia\Blt\Robo\BltTasks;
use Acquia\Blt\Robo\Common\EnvironmentDetector;
use Acquia\Blt\Robo\Common\YamlMunge;
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

    $app = EnvironmentDetector::getAhGroup() ?: 'local';
    $env = EnvironmentDetector::getAhEnv() ?: 'local';
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
        $this->logger->debug("Skipping {$multisite} on AH environment. Database {$db} does not exist.");
        continue;
      }
      else {
        if ($this->getInspector()->isDrupalInstalled()) {
          $this->logger->info("Deploying updates to <comment>{$multisite}</comment>...");

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
            // Define a site-specific cache directory. For some reason, putenv
            // did not work here. This would not be necessary if Drush
            // supported per-site config file loading.
            // @see: https://github.com/drush-ops/drush/pull/4345
            $_ENV['DRUSH_PATHS_CACHE_DIRECTORY'] = "/tmp/.drush-cache-{$app}/{$env}/{$multisite}";
            $this->invokeCommand('drupal:update');
            $this->logger->info("Finished deploying updates to <comment>{$multisite}</comment>.");
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
        $this->logger->info("Deploying updates to <comment>{$multisite}</comment>...");
        $this->taskDrush()->drush('cache:rebuild')->run();
        $this->invokeCommand('drupal:update');
        $this->logger->info("Finished deploying updates to <comment>{$multisite}</comment>.");

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

  /**
   * Replace frontend tests command so we can do more than just a oneline exec.
   *
   * Note that the frontend-test command hook does not need to be in blt.yml.
   *
   * @hook replace-command tests:frontend:run
   */
  public function testsFrontend() {
    if (EnvironmentDetector::isCiEnv()) {
      // We don't want to snapshot develop because it could be unstable.
      if (getenv('TRAVIS_BRANCH') != 'develop') {
        $this->taskExecStack()
          ->dir($this->getConfigValue('repo.root'))
          ->exec('npx percy snapshot --base-url http://localhost:8888 snapshots.yml')
          ->run();
      }
      else {
        $this->logger->notice('Skipping percy snapshot in develop branch.');
      }
    }
    else {
      $this->logger->notice('Skipping percy snapshot in non-CI environment.');
    }
  }

  /**
   * Remove all local settings file beforehand, so they are recreated.
   *
   * The source:build:settings command will only recreate local settings files
   * if they do not already exist. This can be confusing if you change BLT
   * configuration and expect to see the differences in the file.
   *
   * @hook pre-command source:build:settings
   */
  public function preSourceBuildSettings() {
    $yaml = YamlMunge::parseFile($this->getConfigValue('repo.root') . '/blt/local.blt.yml');

    if (isset($yaml['multisites'])) {
      $this->logger->warning('Multisites overridden in local.blt.yml file. Only those sites will have local.settings.php files regenerated.');
    }

    $this->taskExecStack()
      ->dir($this->getConfigValue('docroot'))
      ->exec('rm sites/*/settings/local.settings.php')
      ->exec('rm sites/*/local.drush.yml')
      ->run();
  }

  /**
   * Start chromedriver in CI environment before running Drupal tests.
   *
   * @see https://github.com/acquia/blt-drupal-test/issues/8
   *
   * @hook pre-command tests:drupal:phpunit:run
   */
  public function preTestsDrupalPhpunitRun() {
    if (EnvironmentDetector::isCiEnv()) {
      $this->logger->info("Launching chromedriver...");
      $chromeDriverHost = 'http://localhost';
      $chromeDriverPort = $this->getConfigValue('tests.chromedriver.port');

      $this->getContainer()
        ->get('executor')
        ->execute("chromedriver")
        ->background(TRUE)
        ->printOutput(TRUE)
        ->printMetadata(TRUE)
        ->run();

      $this->getContainer()->get('executor')->waitForUrlAvailable("$chromeDriverHost:{$chromeDriverPort}");
    }
  }

  /**
   * Kill chromedriver in CI after running tests.
   *
   * @hook post-command tests:drupal:phpunit:run
   */
  public function postTestsDrupalPhpunitRun() {
    if (EnvironmentDetector::isCiEnv()) {
      $this->logger->info("Killing running chromedriver processes...");
      $chromeDriverPort = $this->getConfigValue('tests.chromedriver.port');
      $this->getContainer()->get('executor')->killProcessByPort($chromeDriverPort);
    }
  }

}
