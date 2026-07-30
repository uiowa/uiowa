<?php

namespace Uiowa\Blt\Plugin\Commands;

use Acquia\Blt\Robo\BltTasks;
use Acquia\Blt\Robo\Common\YamlMunge;

/**
 * BLT override commands.
 */
class ReplaceCommands extends BltTasks {

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

}
