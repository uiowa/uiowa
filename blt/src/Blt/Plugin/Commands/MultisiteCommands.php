<?php

namespace Uiowa\Blt\Plugin\Commands;

use Acquia\Blt\Robo\BltTasks;
use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Endpoints\Databases;
use AcquiaCloudApi\Endpoints\Domains;
use AcquiaCloudApi\Endpoints\Environments;
use AcquiaCloudApi\Response\OperationResponse;
use SiteNow\Config\Applications;
use SiteNow\Utility\Multisite;
use Uiowa\Blt\AcquiaCloudApiTrait;
use Uiowa\MultisiteTrait;

/**
 * Global multisite commands.
 */
class MultisiteCommands extends BltTasks {

  use AcquiaCloudApiTrait;
  use MultisiteTrait;

  /**
   * Deletes multisite code, database and domains.
   *
   * @param array $options
   *   Array of options.
   *
   * @option simulate
   *   Simulate cloud operations.
   * @option no-commit
   *   Do not create a git commit.
   *
   * @command uiowa:multisite:delete
   *
   * @aliases umd
   *
   * @throws \Exception
   *
   * @requireHost
   * @requireFeatureBranch
   * @requireCredentials
   */
  public function delete(
    array $options = [
      'simulate' => FALSE,
      'no-commit' => FALSE,
    ],
  ) {
    $root = $this->getConfigValue('repo.root');
    $sites = Multisite::getAllSites($root);

    $dir = $this->askChoice('Select which site to delete.', $sites);

    // Load the database name from configuration since that can change from the
    // initial database name but has to match what is in the settings.php file.
    // @see: FileSystemTests.php.
    $this->switchSiteContext($dir);
    $db = $this->getConfigValue('drupal.db.database');

    if ($db != Multisite::getDatabaseName($dir)) {
      throw new \Exception('Database does not match expected value.');
    }

    $id = Multisite::getIdentifier("https://{$dir}");
    $local = Multisite::getInternalDomains($id)['local'];
    $dev = Multisite::getInternalDomains($id)['dev'];
    $test = Multisite::getInternalDomains($id)['test'];
    $prod = Multisite::getInternalDomains($id)['prod'];

    $this->say("Selected site <comment>{$dir}</comment>.");

    $properties = [
      'files' => "docroot/sites/$dir/files",
      'database' => $db,
      'domains' => [
        $dev,
        $test,
        $prod,
        $dir,
      ],
    ];

    $app = $this->getAppForSiteFromManifest($dir);
    $this->printArrayAsTable($properties);

    if (!$options['simulate']) {
      if (!$this->confirm('The cloud properties above will be deleted. Are you sure?', FALSE)) {
        throw new \Exception('Aborted.');
      }
      else {
        /** @var \AcquiaCloudApi\Connector\Client $client */
        $client = $this->getAcquiaCloudApiClient(
          $this->getConfigValue('uiowa.credentials.acquia.key'),
          $this->getConfigValue('uiowa.credentials.acquia.secret')
        );

        $registry = new Applications("{$root}/sitenow/applications.yml");
        $uuid = $registry->uuid($app);
        if (!$uuid) {
          return;
        }

        // Iterate over each environment and delete files.
        foreach (['dev', 'test', 'prod'] as $env) {
          $this->deleteRemoteMultisiteFiles($id, $app, $env, $dir, $client, $uuid);
        }

        /** @var \AcquiaCloudApi\Endpoints\Databases $databases */
        $databases = new Databases($client);

        foreach ($databases->getAll($uuid) as $database) {
          if ($database->name === $db) {
            $databases->delete($uuid, $db);
            $this->say("Deleted <comment>{$db}</comment> cloud database on <comment>{$app}</comment> application.");

            /** @var \AcquiaCloudApi\Endpoints\Environments $environments */
            $environments = new Environments($client);

            foreach ($environments->getAll($uuid) as $environment) {
              if ($intersect = array_intersect($properties['domains'], $environment->domains)) {
                $domains = new Domains($client);

                foreach ($intersect as $domain) {
                  $domains->delete($environment->uuid, $domain);
                  $this->say("Deleted <comment>{$domain}</comment> domain on {$app} application.");
                }
              }
            }

            break;
          }
        }
      }
    }
    else {
      $this->logger->warning('The cloud properties above will not be deleted because you used the --simulate option.');
    }

    // Flag if site configuration exists.
    $site_config = file_exists("{$root}/config/sites/{$dir}");

    // Delete the site code.
    $this->taskFilesystemStack()
      ->remove("{$root}/config/sites/{$dir}")
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

    // Load the manifest.
    $manifest = $this->manifestToArray();

    // Add the site to the manifest.
    $this->removeSiteFromManifest($manifest, $app, $dir);

    // Write the manifest back to the file.
    $this->arrayToManifest($manifest);

    if (!$options['no-commit']) {

      $task = $this->taskGit()
        ->dir($root)
        ->add('docroot/sites/sites.php')
        ->add("docroot/sites/{$dir}/")
        ->add("drush/sites/{$id}.site.yml")
        ->add('blt/manifest.yml')
        ->interactive(FALSE);

      // If site configuration existed, add it to the commit.
      if ($site_config) {
        $task->add("config/sites/{$dir}");
      }

      $task->commit("Delete {$dir} multisite on {$app}")
        ->run();

      $this->say("Committed deletion of site <comment>{$dir}</comment> code.");
    }

    $this->say('Continue deleting additional multisites or push this branch and merge via a pull request. Immediate production release not necessary.');
  }

  /**
   * Wait for a Cloud API operation to complete.
   *
   * @param \AcquiaCloudApi\Response\OperationResponse $operation
   *   The operation to check.
   * @param \AcquiaCloudApi\Connector\Client $client
   *   The API client.
   *
   * @throws \Exception
   */
  protected function waitForOperation(OperationResponse $operation, Client $client) {
    if (!isset($operation->links)) {
      throw new \Exception('Cannot check operation status, no links set.');
    }

    // Get the operation notification URL path and strip the leading 'api/'
    // from it because that is added below when making the request.
    $path = substr(parse_url($operation->links->notification->href, PHP_URL_PATH), 4);
    $this->logger->notice("Waiting for cloud API operation ($operation->message) to complete...");
    do {
      /** @var \AcquiaCloudApi\Response\NotificationResponse $notification */
      $notification = $client->request('GET', $path);
      sleep(3);
    } while ($notification->status == 'in-progress');

    return $notification;
  }

  /**
   * Delete files on application environment.
   *
   * Note that we CD into the file system first and THEN delete the site file
   * directories. If we just rm -rf the directory and $site is ever empty, the
   * entire sites directory would be deleted.
   *
   * @param string $id
   *   The multisite identifier.
   * @param string $app
   *   The application to use for Drush alias.
   * @param string $env
   *   The environment to use for the Drush alias.
   * @param string $site
   *   The multisite files directory to delete.
   * @param \AcquiaCloudApi\Connector\Client $client
   *   The Acquia Cloud API client.
   * @param string $uuid
   *   The application UUID.
   *
   * @throws \Robo\Exception\TaskException
   * @throws \Exception
   */
  protected function deleteRemoteMultisiteFiles(string $id, string $app, string $env, string $site, Client $client, string $uuid): void {
    if ($site == '.' || $site == '*') {
      throw new \Exception('Deleting current directory or wildcard is not allowed.');
    }

    // Handle both old 'test' and alternative 'stage' naming conventions.
    $env_name = $env;
    if ($env === 'test') {
      // Check if the application has a 'stage' environment instead of 'test'.
      try {
        $environments = new Environments($client);
        $envs = $environments->getAll($uuid);
        foreach ($envs as $environment) {
          if ($environment->name === 'stage') {
            $env_name = 'stage';
            break;
          }
        }
      }
      catch (\Exception $e) {
        throw new \Exception("Unable to fetch environments for $app to determine stage/test naming. Error: " . $e->getMessage());
      }
    }

    $app_env = "$app.$env_name";

    $file_directories = [
      'files',
      'files-private',
    ];

    foreach ($file_directories as $directory) {
      $result = $this->taskDrush()
        ->alias("$id.$env")
        ->drush('ssh')
        ->arg("rm -rf $site/$directory/*")
        ->option('cd', "/mnt/gfs/$app_env/sites/")
        ->run();

      if (!$result->wasSuccessful()) {
        throw new \Exception("Unable to delete multisite $directory for $site on $app_env.");
      }
    }
  }

}
