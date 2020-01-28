<?php

namespace Uiowa\Blt\Plugin\Commands\Collegiate;

use Acquia\Blt\Robo\BltTasks;

/**
 * Adds commands in the uiowa:* space.
 */
class MultisiteCommands extends BltTasks {

  /**
   * Checks if a path has been changed.
   *
   * @param string $path
   *   The path to check.
   *
   * @return bool
   *   Whether git is dirty or not.
   */
  public function checkDirty($path) {
    $result = $this->taskExec('git status --porcelain')
      ->printMetadata(FALSE)
      ->printOutput(TRUE)
      ->interactive(FALSE)
      ->run();

    return strpos($result->getMessage(), $path) !== FALSE;
  }

  /**
   * Create the local settings.php file.
   *
   * @command collegiate:settings:local
   *
   * @param1 string $domain
   *
   * @input string $domain
   *
   * @aliases csl
   *
   * @throws \Acquia\Blt\Robo\Exceptions\BltException
   */
  public function createLocalSettingsFile($domain) {
    $filename = 'local.settings.php';
    $copy = TRUE;
    $new = $this->getConfigValue('docroot') . "/sites/$domain/settings/$filename";
    $default = $this->getConfigValue('docroot') . "/sites/default/settings/default.$filename";

    // Get the context for the site.
    $this->switchSiteContext($domain);

    // If settings file exists, ask if it should be overwritten.
    if (file_exists($new) && !$this->confirm('The local.settings.php file already exists. Are you sure you want to overwrite it?')) {
      $copy = FALSE;
    }

    if ($copy) {
      $this->taskFilesystemStack()->copy($default, $new, TRUE)->run();

      $this->getConfig()->expandFileProperties($new);
    }
  }

  /**
   * Install the new multisite.
   *
   * @param string $site_dir
   *   The site directory.
   * @param string $install_profile
   *   The install profile to install.
   * @param array $options
   *   The command options.
   *
   * @return bool
   *   Flag indicating whether the site was installed.
   *
   * @throws \Robo\Exception\TaskException
   */
  protected function installSite($site_dir, $install_profile, array $options) {
    if ($install_site = $this->confirm("Would you like to run site:install for $site_dir?")) {

      $options_map = [
        'sites-subdir' => 'site-dir',
        'account-name' => 'account-name',
        'account-mail' => 'account-mail',
      ];

      foreach ($options_map as $opt => $map) {
        if (!empty($options[$map])) {
          $options_map[$opt] = $options[$map];
        }
        else {
          unset($options_map[$opt]);
        }
      }

      if (!$install_profile) {
        $install_profile = $this->askDefault('Install profile to install', DEFAULT_INSTALL_PROFILE);
      }

      // @todo Validate install profile exists.
      $this->taskDrush()
        ->drush('site:install')
        ->interactive(TRUE)
        ->arg($install_profile)
        ->options($options_map)
        ->run();

      // $this->taskDrush()
      // ->drush('user:role:add')
      // ->args([
      // 'administrator',
      // $uid,
      // ])
      // ->run();
      $this->taskDrush()
        ->drush('config:set')
        ->args([
          'system.site',
          'name',
          $site_dir,
        ])
        ->run();
    }

    return $install_site;
  }
}
