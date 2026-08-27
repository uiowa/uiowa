<?php

namespace Uiowa\Blt\Plugin\Commands;

use Acquia\Blt\Robo\BltTasks;
use Consolidation\AnnotatedCommand\CommandError;

/**
 * BLT override commands.
 */
class ReplaceCommands extends BltTasks {

  /**
   * Block BLT's single-site sync.
   *
   * BLT's sync.commands includes blt:init:settings, which deletes every
   * multisite's local.settings.php and local.drush.yml via the
   * source:build:settings hook below.
   *
   * @hook validate drupal:sync:default:site
   */
  public function validateDrupalSyncDefaultSite() {
    return new CommandError('This command is no longer supported. Use `ddev sn ds SITE` instead.');
  }

  /**
   * Block BLT's all-sites sync.
   *
   * @hook validate drupal:sync:all-sites
   */
  public function validateDrupalSyncAllSites() {
    return new CommandError('This command is no longer supported. Use `ddev sn dsa` instead.');
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

    $this->taskExecStack()
      ->dir($this->getConfigValue('docroot'))
      ->exec('rm sites/*/settings/default.local.settings.php')
      ->exec('rm sites/*/settings/local.settings.php')
      ->exec('rm sites/*/local.drush.yml')
      ->run();
  }

  /**
   * Point each multisite's stage file proxy at a real origin.
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
  }

}
