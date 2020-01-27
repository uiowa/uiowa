<?php

namespace Uiowa\Blt\Plugin\Commands\Sitenow;

use Acquia\Blt\Robo\BltTasks;
use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Connector\Connector;
use AcquiaCloudApi\Endpoints\Databases;
use AcquiaCloudApi\Endpoints\Domains;
use AcquiaCloudApi\Endpoints\Environments;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\CommandError;
use Symfony\Component\Yaml\Yaml;
use Uiowa\Multisite;

/**
 * Defines commands in the Sitenow namespace.
 */
class MultisiteCommands extends BltTasks {

  /**
   * A no-op command.
   *
   * This is called in sync.commands to override the frontend step.
   *
   * @see: https://github.com/acquia/blt/issues/3697
   *
   * @command sitenow:multisite:noop
   *
   * @aliases smn
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
   * @command sitenow:multisite:execute
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
  }

  /**
   * Deletes multisite code, database and domains.
   *
   * @command sitenow:multisite:delete
   *
   * @aliases smd
   *
   * @throws \Exception
   *
   * @requireFeatureBranch
   * @requireCredentials
   */
  public function delete() {
    $root = $this->getConfigValue('repo.root');
    $sites = Multisite::getAllSites($root);

    $dir = $this->askChoice('Select which site to delete.', $sites);

    // Load the database name from configuration since that can change from the
    // initial database name but has to match what is in the settings.php file.
    // @see: FileSystemTests.php.
    $this->switchSiteContext($dir);
    $db = $this->getConfigValue('drupal.db.database');

    $id = Multisite::getIdentifier("https://{$dir}");
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

      // Delete the site code.
      $this->taskFilesystemStack()
        ->remove("{$root}/config/{$dir}")
        ->remove("{$root}/docroot/sites/{$dir}")
        ->remove("{$root}/drush/sites/{$id}.site.yml")
        ->run();

      // Remove the directory aliases from sites.php.
      $contents = file_get_contents("{$root}/docroot/sites/sites.php");

      // Remove sites.php data.
      $data = <<<EOD

// Directory aliases for {$dir}.
\$sites['{$dev}'] = '{$dir}';
\$sites['{$test}'] = '{$dir}';
\$sites['{$prod}'] = '{$dir}';

EOD;

      $contents = str_replace($data, '', $contents);
      file_put_contents("{$root}/docroot/sites/sites.php", $contents);

      $this->taskGit()
        ->dir($root)
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

  /**
   * Validate sitenow:multisite:create command.
   *
   * @hook validate sitenow:multisite:create
   */
  public function validateCreate(CommandData $commandData) {
    $root = $this->getConfigValue('repo.root');
    $host = $commandData->input()->getArgument('host');

    // Lowercase the host, just in case.
    $commandData->input()->setArgument('host', strtolower($host));

    if (parse_url($host, PHP_URL_SCHEME) == NULL) {
      $uri = "https://{$host}";
    }
    else {
      return new CommandError('Only pass the multisite host, i.e. the URI without the protocol.');
    }

    if ($parsed = parse_url($uri)) {
      if (isset($parsed['path'])) {
        return new CommandError('Sitenow: Subdirectory sites are not supported.');
      }
    }
    else {
      return new CommandError('Cannot parse URI for validation.');
    }

    if (file_exists("{$root}/docroot/sites/{$host}")) {
      return new CommandError("Site {$host} already exists.");
    }
  }

  /**
   * Create multisite code, cloud database and domains.
   *
   * @command sitenow:multisite:create
   *
   * @aliases smc
   *
   * @arg $host
   *   The multisite URI host. Will be used as the site directory.
   *
   * @arg $requester
   *   The HawkID of the original requester. Will be granted webmaster access.
   *
   * @requireFeatureBranch
   * @requireCredentials
   *
   * @throws \Exception
   */
  public function create($host, $requester) {
    $db = Multisite::getInitialDatabaseName($host);

    $applications = $this->getConfigValue('uiowa.applications');
    $choices = [];

    foreach ($applications as $app => $attr) {
      $choices[$app] = $attr['id'];
    }

    $choice = $this->askChoice('Which cloud application should be used?', $choices);

    /** @var \AcquiaCloudApi\Connector\Client $client */
    $client = $this->getAcquiaCloudApiClient();

    /** @var \AcquiaCloudApi\Endpoints\Databases $databases */
    $databases = new Databases($client);
    $databases->create($applications[$choice]['id'], $db);
    $this->say("Created <comment>{$db}</comment> cloud database on {$choice}.");

    $id = Multisite::getIdentifier("https://{$host}");
    $dev = Multisite::getInternalDomains($id)['dev'];
    $test = Multisite::getInternalDomains($id)['test'];
    $prod = Multisite::getInternalDomains($id)['prod'];

    $root = $this->getConfigValue('repo.root');

    $this->getConfig()->set('drupal.db.database', $db);
    $this->input()->setInteractive(FALSE);

    $this->invokeCommand('recipes:multisite:init', [
      '--site-uri' => "https://{$host}",
      '--site-dir' => $host,
      '--remote-alias' => "{$id}.prod",
    ]);

    $result = $this->taskReplaceInFile("{$root}/docroot/sites/{$host}/settings.php")
      ->from('require DRUPAL_ROOT . "/../vendor/acquia/blt/settings/blt.settings.php";' . "\n")
      ->to(<<<EOD
\$ah_group = getenv('AH_SITE_GROUP');

if (file_exists('/var/www/site-php')) {
  require "/var/www/site-php/{\$ah_group}/{$db}-settings.inc";
}

require DRUPAL_ROOT . "/../vendor/acquia/blt/settings/blt.settings.php";

EOD
      )
      ->run();

    if (!$result->wasSuccessful()) {
      throw new \Exception("Unable to set database include for site {$host}.");
    }

    file_put_contents("{$root}/docroot/sites/{$host}/settings/includes.settings.php", <<<EOD
<?php

/**
 * @file
 * Generated by BLT. A central aggregation point for adding settings files.
 */

/**
 * Add settings using full file location and name.
 *
 * It is recommended that you use the DRUPAL_ROOT and \$site_dir components to
 * provide full pathing to the file in a dynamic manner.
 */
\$additionalSettingsFiles = [
  DRUPAL_ROOT . "/sites/settings/sitenow.settings.php"
];

foreach (\$additionalSettingsFiles as \$settingsFile) {
  if (file_exists(\$settingsFile)) {
    require \$settingsFile;
  }
}

EOD
    );

    // Remove some files that we probably don't need.
    $files = [
      "{$root}/docroot/sites/{$host}/default.services.yml",
      "{$root}/docroot/sites/{$host}/services.yml",
      "{$root}/drush/sites/{$host}.site.yml",
    ];

    foreach ($files as $file) {
      if (file_exists($file)) {
        unlink($file);
        $this->logger->debug("Deleted {$file}.");
      }
    }

    // Re-generate the Drush alias so it is more useful.
    $app = $this->getConfig()->get('project.prefix');
    $default = Yaml::parse(file_get_contents("{$root}/drush/sites/{$app}.site.yml"));
    $default['local']['uri'] = $host;
    $default['dev']['uri'] = $dev;
    $default['test']['uri'] = $test;
    $default['prod']['uri'] = $host;

    file_put_contents("{$root}/drush/sites/{$id}.site.yml", Yaml::dump($default, 10, 2));
    $this->say("Updated <comment>{$id}.site.yml</comment> Drush alias file with <info>local, dev, test and prod</info> aliases.");

    // Overwrite the multisite blt.yml file.
    $blt = Yaml::parse(file_get_contents("{$root}/docroot/sites/{$host}/blt.yml"));
    $blt['project']['machine_name'] = $id;
    $blt['drupal']['db']['database'] = $db;
    $blt['drush']['aliases']['local'] = 'self';
    $blt['uiowa']['profiles']['sitenow']['requester'] = $requester;
    file_put_contents("{$root}/docroot/sites/{$host}/blt.yml", Yaml::dump($blt, 10, 2));
    $this->say("Overwrote <comment>docroot/sites/{$host}/blt.yml</comment> file with standardized names.");

    // Write sites.php data.
    $data = <<<EOD

// Directory aliases for {$host}.
\$sites['{$dev}'] = '{$host}';
\$sites['{$test}'] = '{$host}';
\$sites['{$prod}'] = '{$host}';

EOD;

    file_put_contents($root . '/docroot/sites/sites.php', $data, FILE_APPEND);
    $this->say('Added default <comment>sites.php</comment> entries.');

    // Remove the new local settings file - it has the wrong database name.
    $file = "{$root}/docroot/sites/{$host}/settings/local.settings.php";

    if (file_exists($file)) {
      unlink($file);
    }

    $this->invokeCommand('blt:init:settings');

    // Create the config directory with a file to commit.
    $this->taskFilesystemStack()
      ->mkdir("{$root}/config/{$host}")
      ->touch("{$root}/config/{$host}/.gitkeep")
      ->run();

    $this->taskGit()
      ->dir($this->getConfigValue("repo.root"))
      ->add('docroot/sites/sites.php')
      ->add("docroot/sites/{$host}")
      ->add("drush/sites/{$id}.site.yml")
      ->add("config/{$host}")
      ->commit("Initialize {$host} multisite")
      ->interactive(FALSE)
      ->printOutput(FALSE)
      ->printMetadata(FALSE)
      ->run();

    $this->say("Committed site <comment>{$host}</comment> code.");
    $this->say("Continue initializing additional multisites or follow the next steps below.");

    $steps = [
      'Push this branch and merge via a pull request.',
      'Coordinate a new release and deploy to the test and prod environments.',
      'Add the multisite domains to environments as needed.',
    ];

    $this->io()->listing($steps);
  }

  /**
   * Unset the routed multisite so Drush/BLT does not bootstrap it.
   *
   * @hook pre-command drupal:sync:all-sites
   */
  public function preSyncAllSites(CommandData $commandData) {
    $this->killServer();
  }

  /**
   * Unset the routed multisite so Drush/BLT does not bootstrap it.
   *
   * @hook pre-command drupal:sync
   */
  public function preSync(CommandData $commandData) {
    $this->killServer();
  }

  /**
   * Validate that the command is being run on a feature branch.
   *
   * @hook validate @requireFeatureBranch
   */
  public function validateFeatureBranch() {
    $result = $this->taskGit()
      ->dir($this->getConfigValue("repo.root"))
      ->exec('git rev-parse --abbrev-ref HEAD')
      ->interactive(FALSE)
      ->printOutput(FALSE)
      ->printMetadata(FALSE)
      ->run();

    $branch = $result->getMessage();

    if ($branch == 'master' || $branch == 'develop') {
      return new CommandError('You must run this command on a feature branch created off master.');
    }
  }

  /**
   * Validate necessary credentials are set.
   *
   * @hook validate @requireCredentials
   */
  public function validateCredentials() {
    $credentials = [
      'uiowa.credentials.acquia.key',
      'uiowa.credentials.acquia.secret',
    ];

    foreach ($credentials as $cred) {
      if (!$this->getConfigValue($cred)) {
        return new CommandError("You must set {$cred} in your {$this->getConfigValue('repo.root')}/blt/local.blt.yml file. DO NOT commit these anywhere in the repository!");
      }
    }
  }

  /**
   * Overwrite the sites.php file with no routed multisite.
   */
  protected function killServer() {
    $root = $this->getConfigValue('repo.root');
    file_put_contents("{$root}/docroot/sites/sites.local.php", "<?php\n");
    $this->getContainer()->get('executor')->killProcessByPort('8888');
    $this->yell('The sites.local.php file has been emptied. Runserver has been stopped.');
  }

  /**
   * Return new ConnectorInterface for Acquia Cloud API v2 interactions.
   *
   * @return \AcquiaCloudApi\Connector\Client
   *   ConnectorInterface client.
   */
  protected function getAcquiaCloudApiClient() {
    $connector = new Connector([
      'key' => $this->getConfigValue('uiowa.credentials.acquia.key'),
      'secret' => $this->getConfigValue('uiowa.credentials.acquia.secret'),
    ]);

    /** @var \AcquiaCloudApi\Connector\Client $client */
    $client = Client::factory($connector);

    return $client;
  }

}
