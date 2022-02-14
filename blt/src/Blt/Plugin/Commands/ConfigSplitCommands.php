<?php

namespace Uiowa\Blt\Plugin\Commands;

use Acquia\Blt\Robo\BltTasks;
use Acquia\Blt\Robo\Common\YamlMunge;
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
   */
  public function updateFeatureSplits() {
    // Reference: https://github.com/uiowa/uiowa/blob/15497457c6c34c3b49c5f4d5cda259a4e67982dc/blt/src/Blt/Plugin/Commands/GitCommands.php#L212-L222
    // @todo Limit to only DDEV.
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

        $this->updateSplit($split);
      }
    }
  }

  /**
   * Validate that the command is not being run on the container.
   *
   * @command uiowa:usplits:site
   */
  public function updateSiteSplits() {
    // @todo Limit to only DDEV.
    $root = $this->getConfigValue('repo.root');
    $split_name = 'config_split.config_split.site.yml';
    $finder = new Finder();

    $splits = $finder
      ->files()
      ->in("$root/config/sites/")
      ->depth('< 2')
      ->name($split_name)
      ->sortByName();

    foreach ($splits->getIterator() as $split) {
      $host = $split->getRelativePath();
      $split = YamlMunge::parseFile($split->getPathname());
      $alias = Multisite::getIdentifier("https://$host");
      $this->switchSiteContext($host);
      $this->updateSplit($split, $alias);
    }
  }

  /**
   * Export a split.
   */
  protected function updateSplit($split, $alias = 'default') {
    $id = $split['id'];

    // Sync default site database.
    $this->taskDrush()
      ->drush('sql:sync')
      ->args([
        "@$alias.prod",
        "@$alias.local",
      ])
      ->stopOnFail()
      ->run();

    $result = $this->taskDrush()
      ->stopOnFail(FALSE)
      ->drush('config:get')
      ->alias("$alias.local")
      ->args("config_split.config_split.{$id}", 'status')
      ->run();

    $status = FALSE;
    if ($result->getExitCode() !== 1 && $result->getMessage() !== '') {
      $status = trim($result->getMessage());
      $status = str_replace("'config_split.config_split.$id:status': ", '', $status);
      $status = $status === 'true';
    }

    // If the split is not enabled, enable it, rebuild cache, and re-import
    // config.
    if (!$status) {
      $this->taskDrush()
        ->stopOnFail(FALSE)
        ->drush('config:set')
        ->args("config_split.config_split.{$id}", 'status', TRUE)
        ->drush('cache:rebuild')
        ->drush('config:import')
        ->run();
    }

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
      ->arg($id)
      ->run();
  }

}
