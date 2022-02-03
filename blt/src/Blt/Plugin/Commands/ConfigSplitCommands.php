<?php

namespace Uiowa\Blt\Plugin\Commands;

use Acquia\Blt\Robo\BltTasks;
use Acquia\Blt\Robo\Common\YamlMunge;
use Symfony\Component\Finder\Finder;

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

    /** @var  $split_file */
    foreach ($split_files->getIterator() as $split_file) {
      if (file_exists($split_file)) {
        $split = YamlMunge::parseFile($split_file);
        $id = $split['id'];

        // Sync default site database.
        $this->taskDrush()
          ->drush('sql:sync')
          ->args([
            "@default.prod",
            "@default.local",
          ])
          ->stopOnFail()
          ->run();

        // @todo Run database updates.

        // Enable feature split, rebuild cache, and import config.
        $this->taskDrush()
          ->stopOnFail(FALSE)
          ->drush('config:set')
          ->args("config_split.config_split.{$id}", 'status', TRUE)
          ->drush('cache:rebuild')
          ->drush('config:import')
          ->run();

        // Re-export the split.
        $this->taskDrush()
          ->stopOnFail(FALSE)
          ->drush('config-split:export')
          ->arg($id)
          ->run();
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
    $finder = new Finder();
    // @todo Get all the site config splits.
    $split_directories = $finder
      ->directories()
      ->in("$root/config/sites/")
      ->depth('< 2')
      ->sortByName();

    //    foreach ($split_directories->getIterator() as $split_directory) {
    // @todo Get the site URL from the directory name.
    //      $host = '';
    // @todo Sync each site.
    //      $this->invokeCommand('drupal:sync', [
    //        '--site' => $host,
    //      ]);
    // @todo Export site split.
    //      $this->taskDrush();
    //    }
  }

}
