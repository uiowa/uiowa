<?php

namespace Uiowa\Blt\Plugin\Commands;

use Acquia\Blt\Robo\BltTasks;
use Acquia\Blt\Robo\Common\EnvironmentDetector;

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

    foreach ($this->getConfigValue('multisites') as $multisite) {
      $this->switchSiteContext($multisite);
      $profile = $this->getConfigValue('project.profile.name');
      $db = $this->getConfigValue('drupal.db.database');

      // Check for database include on this application.
      if (EnvironmentDetector::isAhEnv() || EnvironmentDetector::isLocalEnv()) {
        $app = EnvironmentDetector::getAhGroup();

        if (file_exists("/var/www/site-php/{$app}/{$db}-settings.inc")) {

          if ($this->getInspector()->isDrupalInstalled()) {
            $this->say("Deploying updates to <comment>$multisite</comment>...");
            $this->invokeCommand('drupal:update');
            $this->say("Finished deploying updates to $multisite.");
          }
          else {
            $this->logger->warning("Drupal not installed for <comment>$multisite</comment>. Installing from configuration in sitenow profile....");
            $uri = $this->getConfig()->get('site');

            if (empty($uri)) {
              throw new \Exception('Cannot determine site directory for installation.');
            }

            $uid = uniqid('admin_');

            $site_install_options = [
              'sites-subdir' => $uri,
              'account-name' => $uid,
              'account-mail' => base64_decode('aXRzLXdlYkB1aW93YS5lZHU='),
            ];

            // If this is the sitenow profile, set the existing-config option.
            if ($profile === 'sitenow') {
              $site_install_options['existing-config'] = NULL;
            }

            $result = $this->taskDrush()
              ->stopOnFail(TRUE)
              ->drush('site:install')
              ->arg($profile)
              ->options($site_install_options)
              ->drush('user:role:add')
              ->args([
                'administrator',
                $uid,
              ])
              ->drush('config:set')
              ->args([
                'system.site',
                'name',
                $uri,
              ])
              ->run();

            if (!$result->wasSuccessful()) {
              throw new \Exception("Site install task failed for {$uri}.");
            }

            // If a requester was added, add them as a webmaster for the site.
            if ($requester = $this->getConfigValue('uiowa.profiles.sitenow.requester')) {
              $result = $this->taskDrush()
                ->stopOnFail(FALSE)
                ->drush('user:create')
                ->args($requester)
                ->drush('user:role:add')
                ->args([
                  'webmaster',
                  $requester,
                ])
                ->run();

              if (!$result->wasSuccessful()) {
                throw new \Exception("Webmaster task failed for {$uri}.");
              }
            }
          }
        }
        else {
          $this->say("Skipping $multisite. Database {$db} does not exist.");
        }
      }
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
   * Replace blt setup command.
   *
   * This allows CI to test multiple install profiles using the default site
   * which is not used.
   *
   */
  public function replaceSetup() {
    if (EnvironmentDetector::isCiEnv()) {
      $this->invokeCommands([
        'source:build',
        'drupal:deployment-identifier:init',
      ]);

      foreach ($this->getConfigValue('uiowa.profiles') as $profile => $data) {
        $this->say("Installing {$profile} profile on site <comment>{$data['ci_site']}</comment>.");

        // Disable alias since we are targeting a specific URI.
        $this->config->set('drush.alias', '');

        $this->switchSiteContext($data['ci_site']);
        $this->invokeCommand('drupal:install', ['--site' => $data['ci_site']]);
        $this->switchSiteContext('default');
      }

      $this->invokeCommand('blt:init:shell-alias');
    }
    else {
      $this->say("Setting up local environment for site <comment>{$this->getConfigValue('site')}</comment>.");
      if ($this->getConfigValue('drush.alias')) {
        $this->say("Using drush alias <comment>@{$this->getConfigValue('drush.alias')}</comment>");
      }

      $this->invokeCommands([
        'source:build',
        'drupal:deployment-identifier:init',
        'drupal:install',
        'drupal:toggle:modules',
      ]);
    }
  }

}
