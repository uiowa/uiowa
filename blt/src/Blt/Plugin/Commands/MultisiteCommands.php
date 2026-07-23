<?php

namespace Uiowa\Blt\Plugin\Commands;

use Acquia\Blt\Robo\BltTasks;
use Acquia\Blt\Robo\Common\EnvironmentDetector;
use Acquia\Blt\Robo\Exceptions\BltException;
use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Endpoints\Databases;
use AcquiaCloudApi\Endpoints\Domains;
use AcquiaCloudApi\Endpoints\Environments;
use AcquiaCloudApi\Response\OperationResponse;
use Consolidation\AnnotatedCommand\CommandData;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ClientException;
use SiteNow\Utility\Multisite;
use Uiowa\MultisiteTrait;

/**
 * Global multisite commands.
 */
class MultisiteCommands extends BltTasks {

  use MultisiteTrait;

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
   *
   * @hidden
   */
  public function noop() {

  }

  /**
   * Invoke the BLT install process on multisites where Drupal is not installed.
   *
   * @param array $options
   *   Command options.
   *
   * @option envs
   *   Array of allowed environments for installation to happen on.
   * @option dry-run
   *   Report back the uninstalled sites but do not install.
   *
   * @command uiowa:multisite:install
   *
   * @aliases umi
   *
   * @return mixed
   *   CommandError, list of uninstalled sites or the output from installation.
   *
   * @throws \Exception
   *
   * @see: Acquia\Blt\Robo\Commands\Drupal\InstallCommand
   */
  public function install(
    array $options = [
      'envs' => [
        'local',
        'prod',
      ],
      'dry-run' => FALSE,
    ],
  ) {
    $app = EnvironmentDetector::getAhGroup() ?: 'local';
    $env = EnvironmentDetector::getAhEnv() ?: 'local';

    if (!in_array($env, $options['envs'])) {
      $allowed = implode(', ', $options['envs']);
      return new CommandError("Multisite installation not allowed on {$env} environment. Must be one of {$allowed}. Use option to override.");
    }

    $multisites = $this->getConfigValue('multisites');

    $this->say('Finding uninstalled sites...');
    $progress = $this->io()->createProgressBar();
    $progress->setMaxSteps(count($multisites));
    $progress->start();

    $uninstalled = [];

    foreach ($multisites as $multisite) {
      $progress->advance();
      $this->switchSiteContext($multisite);
      $db = $this->getConfigValue('drupal.db.database');

      // Skip sites whose database do not exist on the application in AH env.
      if (EnvironmentDetector::isAhEnv() && !file_exists("/var/www/site-php/{$app}/{$db}-settings.inc")) {
        $this->logger->info("Skipping {$multisite}. Database {$db} does not exist.");
        continue;
      }

      if (!$this->isDrupalInstalled($multisite)) {
        $uninstalled[] = $multisite;
      }
    }

    $progress->finish();

    if (!empty($uninstalled)) {
      $this->io()->listing($uninstalled);

      if (!$options['dry-run']) {
        if ($this->confirm('You will invoke the drupal:install command for the sites listed above. Are you sure?')) {
          $uninstalled_list = implode(', ', $uninstalled);
          $this->sendNotification("Command `uiowa:multisite:install` *started* for {$uninstalled_list} on {$app} {$env}.");

          foreach ($uninstalled as $multisite) {
            $this->switchSiteContext($multisite);

            // Clear the cache first to prevent random errors on install.
            // We use exec here to always return 0 since the command can fail
            // and cause confusion with the error message output.
            $this->taskExecStack()
              ->interactive(FALSE)
              ->silent(TRUE)
              ->exec("./vendor/bin/drush -l {$multisite} cache:rebuild || true")
              ->run();

            // Run this non-interactively so prompts are bypassed. Note that
            // a file permission exception is thrown on AC so we have to
            // catch that and proceed with the command.
            // @see: https://github.com/acquia/blt/issues/4054
            $this->input()->setInteractive(FALSE);

            try {
              $this->invokeCommand('drupal:install', [
                '--site' => $multisite,
              ]);
            }
            catch (BltException $e) {
              $this->say('<comment>Note:</comment> file permission error on Acquia Cloud can be safely ignored.');
            }
          }

          $this->sendNotification("Command `uiowa:multisite:install` *finished* for {$uninstalled_list} on {$app} {$env}.");
        }
        else {
          throw new \Exception('Canceled.');
        }
      }
    }
    else {
      $this->say('There are no uninstalled sites.');
    }
  }

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

        $uuids = $this->getConfigValue('uiowa.applications');
        if (!array_key_exists($app, $uuids)) {
          return;
        }

        $uuid = $uuids[$app];

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
   * Run post-install tasks.
   *
   * @throws \Robo\Exception\TaskException
   *
   * @hook post-command drupal:install
   */
  public function postDrupalInstall($result, CommandData $commandData) {
    if ($multisite = $this->input->getOption('site')) {
      $this->switchSiteContext($multisite);

      // The site name option used during drush site:install is
      // overwritten if installed from existing configuration.
      $site_name = ($this->getConfigValue('uiowa.site-name') ? $this->getConfigValue('uiowa.site-name') : $multisite);
      $this->taskDrush()
        ->stopOnFail(FALSE)
        ->drush('config:set')
        ->args([
          'system.site',
          'name',
          $site_name,
        ])
        ->run();

      // If a requester was added, add them as a webmaster for the site.
      if ($requester = $this->getConfigValue('uiowa.requester')) {
        $this->taskDrush()
          ->stopOnFail(FALSE)
          ->drush('user:create')
          ->args($requester)
          ->drush('user:role:add')
          ->args([
            'webmaster',
            $requester,
          ])
          ->run();
      }

      // Activate and import any config splits.
      if ($split = $this->getConfigValue('uiowa.config.split')) {
        $splits = is_array($split) ? $split : [$split];
        $task = $this->taskDrush()->stopOnFail(FALSE);
        foreach ($splits as $split_name) {
          $task->drush('config:set')->args("config_split.config_split.{$split_name}", 'status', TRUE);
        }
        $task->drush('cache:rebuild')->drush('config:import')->run();
      }
    }
  }

  /**
   * Send a Slack notification if the webhook environment variable exists.
   *
   * @param string $message
   *   The message to send.
   */
  protected function sendNotification($message) {
    $env = EnvironmentDetector::getAhEnv() ?: 'local';
    $webhook_url = getenv('SLACK_WEBHOOK_URL');

    if ($webhook_url && $env == 'prod' || $env == 'local') {
      $data = [
        'username' => 'Acquia Cloud',
        'text' => $message,
      ];

      $client = new GuzzleClient();

      try {
        $client->post($webhook_url, [
          'body' => json_encode($data),
        ]);
      }
      catch (ClientException $e) {
        $this->logger->warning('Error attempting to send Slack notification: ' . $e->getMessage());
      }
    }
    else {
      $this->logger->warning("Slack webhook URL not configured. Cannot send message: {$message}");
    }
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
