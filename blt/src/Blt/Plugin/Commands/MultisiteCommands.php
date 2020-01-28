<?php

namespace Uiowa\Blt\Plugin\Commands;

use Acquia\Blt\Robo\BltTasks;
use AcquiaCloudApi\Endpoints\Databases;
use AcquiaCloudApi\Endpoints\Domains;
use AcquiaCloudApi\Endpoints\Environments;
use Uiowa\Multisite;

/**
 * Global multisite commands.
 */
class MultisiteCommands extends BltTasks {

  /**
   * A no-op command.
   *
   * This is called in sync.commands to override the frontend step.
   *
   * Compiling frontend assets on a per-site basis is not necessary since we
   * use Yarn workspaces for that. This allows for faster syncs.
   *
   * @see: https://github.com/acquia/blt/issues/3697
   *
   * @command uiowa:multisite:noop
   *
   * @aliases umn
   */
  public function noop() {

  }

  /**
   * Execute a Drush command against all multisites.
   *
   * @param string $cmd
   *   The simple Drush command to execute, e.g. 'cron' or 'cache:rebuild'. No
   *    support for options or arguments at this time.
   *
   * @command uiowa:multisite:execute
   *
   * @aliases sme
   *
   * @throws \Exception
   */
  public function execute($cmd) {
    if (!$this->confirm("You will execute 'drush {$cmd}' on all multisites. Are you sure?", TRUE)) {
      throw new \Exception('Aborted.');
    }
    else {
      foreach ($this->getConfigValue('multisites') as $multisite) {
        $this->switchSiteContext($multisite);

        $this->taskDrush()
          ->drush($cmd)
          ->run();
      }
    }
  }/**
 * Deletes multisite code, database and domains.
 *
 * @param array $options
 *   Array of options.
 *
 * @option simulate
 *   Simulate cloud operations and file system tasks.
 *
 * @command uiowa:multisite:delete
 *
 * @aliases smd
 *
 * @throws \Exception
 *
 * @requireFeatureBranch
 * @requireCredentials
 */
  public function delete(array $options = ['simulate' => FALSE]) {
    $root = $this->getConfigValue('repo.root');
    $sites = Multisite::getAllSites($root);

    $dir = $this->askChoice('Select which site to delete.', $sites);

    // Load the database name from configuration since that can change from the
    // initial database name but has to match what is in the settings.php file.
    // @see: FileSystemTests.php.
    $this->switchSiteContext($dir);
    $db = $this->getConfigValue('drupal.db.database');

    $id = Multisite::getIdentifier("https://{$dir}");
    $local = Multisite::getInternalDomains($id)['local'];
    $dev = Multisite::getInternalDomains($id)['dev'];
    $test = Multisite::getInternalDomains($id)['test'];
    $prod = Multisite::getInternalDomains($id)['prod'];

    $this->say("Selected site <comment>{$dir}</comment>.");

    $properties = [
      'database' => $db,
      'domains' => [
        $dev,
        $test,
        $prod,
        $dir,
      ],
    ];

    $this->printArrayAsTable($properties);
    if (!$this->confirm("The cloud properties above will be deleted. Are you sure?", FALSE)) {
      throw new \Exception('Aborted.');
    }
    else {
      if (!$options['simulate']) {
        /** @var \AcquiaCloudApi\Connector\Client $client */
        $client = $this->getAcquiaCloudApiClient();

        foreach ($this->getConfigValue('uiowa.applications') as $app => $attrs) {
          /** @var \AcquiaCloudApi\Endpoints\Databases $databases */
          $databases = new Databases($client);

          // Find the application that hosts the database.
          foreach ($databases->getAll($attrs['id']) as $database) {
            if ($database->name == $db) {
              $databases->delete($attrs['id'], $db);
              $this->say("Deleted <comment>{$db}</comment> cloud database on <comment>{$app}</comment> application.");

              /** @var \AcquiaCloudApi\Endpoints\Environments $environments */
              $environments = new Environments($client);

              foreach ($environments->getAll($attrs['id']) as $environment) {
                if ($intersect = array_intersect($properties['domains'], $environment->domains)) {
                  $domains = new Domains($client);

                  foreach ($intersect as $domain) {
                    $domains->delete($environment->uuid, $domain);
                    $this->say("Deleted <comment>{$domain}</comment> domain on {$app} application.");
                  }
                }
              }

              break 2;
            }
          }
        }
      }
      else {
        $this->logger->warning('Skipping cloud operations.');
      }

      // Delete the site code.
      $this->taskFilesystemStack()
        ->remove("{$root}/config/{$dir}")
        ->remove("{$root}/docroot/sites/{$dir}")
        ->remove("{$root}/drush/sites/{$id}.site.yml")
        ->run();

      // Remove the directory aliases from sites.php.
      $this->taskReplaceInFile("{$root}/docroot/sites/sites.php")
        ->from(<<<EOD

// Directory aliases for {$dir}.
\$sites['{$local}'] = '{$dir}';
\$sites['{$dev}'] = '{$dir}';
\$sites['{$test}'] = '{$dir}';
\$sites['{$prod}'] = '{$dir}';

EOD
        )
        ->to('')
        ->run();

      // Remove vhost.
      $this->taskReplaceInFile("{$root}/box/config.yml")
        ->from(<<<EOD
-
    servername: {$local}
    documentroot: '{{ drupal_core_path }}'
    extra_parameters: '{{ apache_vhost_php_fpm_parameters }}'
EOD
        )
        ->to('')
        ->run();

      $this->taskGit()
        ->dir($root)
        ->add('box/config.yml')
        ->add('docroot/sites/sites.php')
        ->add("docroot/sites/{$dir}/")
        ->add("drush/sites/{$id}.site.yml")
        ->add("config/{$dir}/")
        ->commit("Delete {$dir} multisite")
        ->interactive(FALSE)
        ->printOutput(FALSE)
        ->printMetadata(FALSE)
        ->run();

      $this->say("Committed deletion of site <comment>{$dir}</comment> code.");
      $this->say("Continue deleting additional multisites or push this branch and merge via a pull request. Immediate production release not necessary.");
    }
  }

}
