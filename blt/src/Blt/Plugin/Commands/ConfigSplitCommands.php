<?php

namespace Uiowa\Blt\Plugin\Commands;

use Acquia\Blt\Robo\BltTasks;
use Acquia\Blt\Robo\Common\YamlMunge;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Finder\Finder;
use Uiowa\Multisite;

/**
 * This class should contain hooks that are used in other commands.
 */
class ConfigSplitCommands extends BltTasks {

  /**
   * Validate that the command is not being run on the container.
   *
   * @command uiowa:usplits:feature
   *
   * @requireContainer
   */
  public function updateFeatureSplits($options = [
    'split' => InputOption::VALUE_OPTIONAL,
    'skip-export' => FALSE,
  ]) {
    // Reference: https://github.com/uiowa/uiowa/blob/15497457c6c34c3b49c5f4d5cda259a4e67982dc/blt/src/Blt/Plugin/Commands/GitCommands.php#L212-L222
    $root = $this->getConfigValue('repo.root');
    $finder = new Finder();

    // Get all the config split features.
    $split_files = $finder
      ->files()
      ->in("$root/config/default/")
      ->depth('< 2')
      ->name('config_split.config_split.*.yml')
      ->sortByName();

    foreach ($split_files->getIterator() as $split_file) {
      if (file_exists($split_file)) {
        $split = YamlMunge::parseFile($split_file);
        $id = $split['id'];

        if (isset($options['split']) && $options['split'] !== $id) {
          continue;
        }

        if (NULL !== $id = $this->getSplitId($split)) {
          $this->setupSplit($id);

          if (!isset($options['skip-export']) || $options['skip-export'] === FALSE) {
            $this->updateSplit($id);
          }
        }
      }
    }
  }

  /**
   * Validate that the command is not being run on the container.
   *
   * @command uiowa:usplits:migrate
   *
   * @requireContainer
   */
  public function updateMigrateSplits() {
    // Reference: https://github.com/uiowa/uiowa/blob/15497457c6c34c3b49c5f4d5cda259a4e67982dc/blt/src/Blt/Plugin/Commands/GitCommands.php#L212-L222
    $root = $this->getConfigValue('repo.root');
    $finder = new Finder();

    // Get all the config split features.
    $split_files = $finder
      ->files()
      ->in("$root/docroot/sites/")
      ->name('config_split.config_split.*.yml')
      ->sortByName();

    foreach ($split_files->getIterator() as $split_file) {
      if (file_exists($split_file)) {
        // This assumes the split is stored in module/name/config/split dir and
        // the finder context in() does not change.
        $host = dirname($split_file->getRelativePath(), 4);
        $module = basename(dirname($split_file->getRelativePath(), 2));
        $split = YamlMunge::parseFile($split_file->getPathname());
        $alias = Multisite::getIdentifier("https://$host");
        $this->switchSiteContext($host);
        if (NULL !== $id = $this->getSplitId($split)) {
          $this->setupSplit($id, $alias, $module);
          $this->updateSplit($id, $alias);
        }
      }
    }
  }

  /**
   * Validate that the command is not being run on the container.
   *
   * @command uiowa:usplits:site
   *
   * @requireContainer
   */
  public function updateSiteSplits($options = [
    'host' => InputOption::VALUE_OPTIONAL,
    'skip-export' => FALSE,
  ]) {
    $root = $this->getConfigValue('repo.root');
    $split_name = 'config_split.config_split.site.yml';
    $finder = new Finder();

    $split_files = $finder
      ->files()
      ->in("$root/config/sites/")
      ->depth('< 2')
      ->name($split_name)
      ->sortByName();

    foreach ($split_files->getIterator() as $split_file) {
      // This assumes the finder in() context above does not change.
      $host = $split_file->getRelativePath();
      $split = YamlMunge::parseFile($split_file->getPathname());
      if (isset($options['host']) && $options['host'] !== $host) {
        continue;
      }
      $alias = Multisite::getIdentifier("https://$host");
      $this->switchSiteContext($host);
      if (NULL !== $id = $this->getSplitId($split)) {
        $this->setupSplit($id, $alias);
        if (!isset($options['skip-export']) || $options['skip-export'] === FALSE) {
          $this->updateSplit($id, $alias);
        }
      }
    }
  }

  /**
   * Sync a site and make sure that the split is installed.
   */
  protected function setupSplit($split_id, $alias = 'default', $module = NULL) {
    $this->say("Setting up the <comment>$split_id</comment> config split on $alias.");

    // Recreate the database in case this site has never been blt-synced before.
    $this->taskDrush()
      ->stopOnFail()
      ->drush('sql:create')
      ->drush('sql:sync')
      ->args([
        "@$alias.prod",
        "@$alias.local",
      ])
      ->drush('cache:rebuild')
      ->drush('config:import')
      ->drush('config:import')
      ->run();

    if ($module) {
      $this->taskDrush()
        ->drush('pm:enable')
        ->arg($module)
        ->run();
    }

    $enable_splits = [
      $split_id,
    ];


    $dependencies = $this->getConfigValue("uiowa.development.config_split.splits.$split_id.dependencies");
    if (is_array($dependencies)) {
      $enable_splits = array_merge($dependencies, $enable_splits);
    }

    foreach ($enable_splits as $enable_split_id) {

      $result = $this->taskDrush()
        ->stopOnFail(FALSE)
        ->drush('config:get')
        ->alias("$alias.local")
        ->args("config_split.config_split.{$enable_split_id}", 'status')
        ->run();

      $status = FALSE;
      if ($result->getExitCode() !== 1 && $result->getMessage() !== '') {
        $status = trim($result->getMessage());
        $status = str_replace("'config_split.config_split.$enable_split_id:status': ", '', $status);
        $status = $status === 'true';
      }

      // If the split is not enabled, enable it, rebuild cache, and re-import
      // config.
      if (!$status) {
        $this->taskDrush()
          ->stopOnFail(FALSE)
          ->drush('config:set')
          ->args("config_split.config_split.{$enable_split_id}", 'status', TRUE)
          ->drush('cache:rebuild')
          ->drush('config:import')
          ->drush('config:import')
          ->drush('config:status')
          ->run();
      }
    }
  }

  /**
   * Export a split.
   */
  protected function updateSplit($split_id, $alias = 'default') {
    $this->say("Updating the <comment>$split_id</comment> config split on $alias.");

    // Run database updates after config.
    $this->taskDrush()
      ->stopOnFail()
      ->drush('updb')
      ->alias("$alias.local")
      ->run();

    // Re-export the split.
    $this->taskDrush()
      ->stopOnFail(FALSE)
      ->drush('config-split:export')
      ->alias("$alias.local")
      ->arg($split_id)
      ->run();
  }

  /**
   * Get the Split ID or NULL.
   */
  protected function getSplitId($split) {
    return $split['id'] ?? NULL;
  }

}
