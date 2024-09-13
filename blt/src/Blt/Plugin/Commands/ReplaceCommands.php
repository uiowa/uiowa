<?php

namespace Uiowa\Blt\Plugin\Commands;

use Acquia\Blt\Robo\BltTasks;
use Acquia\Blt\Robo\Common\EnvironmentDetector;
use Acquia\Blt\Robo\Common\YamlMunge;
use Acquia\Blt\Robo\Exceptions\BltException;
use Uiowa\InspectorTrait;
use Uiowa\MultisiteTrait;

/**
 * BLT override commands.
 */
class ReplaceCommands extends BltTasks {
  use InspectorTrait;
  use MultisiteTrait;

  /**
   * Replace the artifact:update:drupal:all-sites BLT command.
   *
   * @hook replace-command artifact:update:drupal:all-sites
   */
  public function replaceDrupalUpdateAll() {
    // Disable alias since we are targeting a specific URI.
    $this->config->set('drush.alias', '');

    $app = EnvironmentDetector::getAhGroup() ?: 'local';
    $multisite_exception = FALSE;

    // If this is running locally, pull list of sites from config. Otherwise,
    // get the list of sites from the manifest.
    if ($app === 'local') {
      $multisites = $this->getConfigValue('multisites');
      $log_dir = '/tmp/';
    }
    else {
      // Load the manifest.
      $manifest = $this->manifestToArray();
      // If the manifest is empty, log a warning and continue.
      if (!isset($manifest[$app])) {
        $this->logger->warning('No multisites found in manifest for application: ' . $app);
      }
      $multisites = $manifest[$app] ?: [];
      $log_dir = '/shared/logs/';
    }

    // Unshift sites to the beginning to run first.
    $run_first = $this->getConfigValue('uiowa.run_first');

    if ($run_first) {
      // Reverse for foreach so that first listed in config is run first.
      $run_first = array_reverse($run_first);

      foreach ($run_first as $site) {
        if ($key = array_search($site, $multisites)) {
          unset($multisites[$key]);
          array_unshift($multisites, $site);
        }
      }
    }

    $parallel_installed = $this->taskExec('command -v parallel')
      ->printMetadata(FALSE)
      ->printOutput(FALSE)
      ->run();

    $datetime = date('Ymd_His');
    $log_file = $log_dir . 'parallel_deploy_log_' . $app . '_' . $datetime . '.log';

    // Check if the parallel command exists.
    if (trim($parallel_installed->getMessage())) {
      $this->say('Running multisite updates in parallel.');

      // Run site updates in parallel, logging output to terminal and file.
      $command = 'parallel -j 3 blt uiowa:site:update ::: ' . implode(' ', array_map('escapeshellarg', $multisites)) . " 2>&1 | tee -a $log_file";

      $this->taskExec($command)
        ->interactive(FALSE)
        ->run();

      // After running, check the log file for errors using grep.
      $grep_command = "grep -i 'error' $log_file";
      $grep_result = $this->taskExec($grep_command)
        ->printOutput(FALSE)
        ->run()
        ->getMessage();

      // Set the exception flag if grep found any error messages.
      $multisite_exception = !empty($grep_result);
    }
    else {
      $this->say('Running multisite updates sequentially.');
      foreach ($multisites as $multisite) {
        $success = $this->updateSite($multisite, $app);
        if (!$success) {
          $multisite_exception = TRUE;
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
      "{$docroot}/sites/" => "$docroot/sites/simpletest,$docroot/sites/default/files",
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
    if (!$this->confirm('This will delete all local.settings.php files for all multisites. Are you sure?', TRUE)) {
      throw new \Exception('Aborted.');
    }

    $file = $this->getConfigValue('repo.root') . '/blt/local.blt.yml';
    $yaml = YamlMunge::parseFile($file);

    if (isset($yaml['multisites']) && !empty($yaml['multisites'])) {
      $this->logger->info('Multisites overridden in local.blt.yml file. Copying to temporary config.');

      $this->taskFilesystemStack()
        ->copy($file, $this->getConfigValue('repo.root') . '/tmp/local.blt.yml')
        ->stopOnFail(TRUE)
        ->run();

      unset($yaml['multisites']);
      YamlMunge::writeFile($file, $yaml);
    }

    $this->taskExecStack()
      ->dir($this->getConfigValue('docroot'))
      ->exec('rm sites/*/settings/default.local.settings.php')
      ->exec('rm sites/*/settings/local.settings.php')
      ->exec('rm sites/*/local.drush.yml')
      ->run();
  }

  /**
   * Copy any temporary multisites config back from pre-command hook.
   *
   * @hook post-command source:build:settings
   */
  public function postSourceBuildSettings() {
    $root = $this->getConfigValue('repo.root');

    foreach ($this->getConfigValue('multisites') as $site) {
      $this->switchSiteContext($site);
      $origin = $this->getConfigValue('uiowa.stage_file_proxy.origin');

      if (!$origin) {
        $origin = 'https://' . $this->getConfigValue('site');
      }

      $text = <<<EOD

\$config['stage_file_proxy.settings']['origin'] = '$origin';
EOD;

      $this->taskWriteToFile("$root/docroot/sites/$site/settings/local.settings.php")
        ->append()
        ->text($text)
        ->run();
    }

    $from = "$root/tmp/local.blt.yml";

    if (file_exists($from)) {
      $to = "$root/blt/local.blt.yml";

      $this->taskFilesystemStack()
        ->stopOnFail(TRUE)
        ->remove($to)
        ->copy($from, $to)
        ->remove($from)
        ->run();
    }
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

  /**
   * Update a single site.
   *
   * This is essentially a command wrapper for the updateSite method.
   *
   * @param string $site
   *   The site to update.
   *
   * @command uiowa:site:update $site
   *
   * @throws \Robo\Exception\TaskException
   */
  public function updateSingleSite($site) {
    $app = EnvironmentDetector::getAhGroup() ?: 'local';
    $this->updateSite($site, $app);
  }

  /**
   * Run updates for a single site.
   *
   * @param string $site
   *   The site to update.
   * @param string $app
   *   The environment the site is being updated in.
   *
   * @throws \Robo\Exception\TaskException
   */
  protected function updateSite(string $site, string $app = 'local'): bool {
    $this->switchSiteContext($site);
    $db = $this->getConfigValue('drupal.db.database');

    // Check for database include on this application.
    if (EnvironmentDetector::isAhEnv() && !file_exists("/var/www/site-php/{$app}/{$db}-settings.inc")) {
      $this->writeln("Skipping {$site} on AH environment. Database {$db} does not exist.");
    }
    else {
      if ($this->isDrupalInstalled($site)) {
        $this->say("Deploying updates to <comment>{$site}</comment>...");

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
          // Clear the plugin cache for discovery and potential layout issue.
          // @see: https://github.com/uiowa/uiowa/issues/3585.
          $this->taskDrush()
            ->drush('cc plugin')
            ->run();

          $this->invokeCommand('drupal:update');
          $this->say("Finished deploying updates to <comment>{$site}</comment>.");
        }
        catch (BltException $e) {
          $this->say("Failed deploying updates to {$site}.");
          return FALSE;
        }
      }
      else {
        $this->writeln("Skipping {$site}. Drupal is not installed.");
      }
    }
    return TRUE;
  }

}
