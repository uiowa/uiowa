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
   * @param array $options
   *   Array of options.
   *
   * @option simulate
   *   Simulate cloud operations and file system tasks.
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
   * @param string $host
   *   The multisite URI host. Will be used as the site directory.
   * @param string $requester
   *   The HawkID of the original requester. Will be granted webmaster access.
   * @param array $options
   *   An option that takes multiple values.
   *
   * @option simulate
   *   Simulate database creation and filesystem operations.
   * @option no-db
   *   Do not create a cloud database.
   *
   * @command sitenow:multisite:create
   *
   * @aliases smc
   *
   * @requireFeatureBranch
   * @requireCredentials
   *
   * @throws \Exception
   */
  public function create($host, $requester, array $options = ['simulate' => FALSE, 'no-db' => FALSE]) {
    $db = Multisite::getInitialDatabaseName($host);
    $applications = $this->getConfigValue('uiowa.applications');
    $app = $this->askChoice('Which cloud application should be used?', array_keys($applications));

    /** @var \AcquiaCloudApi\Connector\Client $client */
    $client = $this->getAcquiaCloudApiClient();

    /** @var \AcquiaCloudApi\Endpoints\Databases $databases */
    $databases = new Databases($client);

    if (!$options['simulate'] && !$options['no-db']) {
      $databases->create($applications[$app]['id'], $db);
      $this->say("Created <comment>{$db}</comment> cloud database on {$app}.");
    }
    else {
      $this->logger->warning('Skipping database creation.');
    }

    $id = Multisite::getIdentifier("https://{$host}");
    $local = Multisite::getInternalDomains($id)['local'];
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

    // BLT RMI uses the site-dir option for the vhost. Replace with local.
    $result = $this->taskReplaceInFile("{$root}/box/config.yml")
      ->from("servername: {$host}")
      ->to("servername: {$local}")
      ->run();

    if (!$result->wasSuccessful()) {
      throw new \Exception("Unable to replace DrupalVM vhost for {$host}.");
    }

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

    // Copy the default settings include to documented name.
    $result = $this->taskWriteToFile("{$root}/docroot/sites/{$host}/settings/includes.settings.php")
      ->text(<<<EOD
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
    )
      ->run();

    if (!$result->wasSuccessful()) {
      throw new \Exception("Unable to set settings include for site {$host}.");
    }

    // Remove some files that we don't need or will be regenerated below.
    $files = [
      "{$root}/drush/sites/default.site.yml",
      "{$root}/docroot/sites/{$host}/default.services.yml",
      "{$root}/docroot/sites/{$host}/services.yml",
      "{$root}/drush/sites/{$host}.site.yml",
      "{$root}/docroot/sites/{$host}/settings/local.settings.php",
    ];

    $this->taskFilesystemStack()
      ->remove($files)
      ->run();

    // Re-generate the Drush alias so it is more useful.
    $default = Yaml::parse(file_get_contents("{$root}/drush/sites/{$app}.site.yml"));
    $default['local']['uri'] = $local;
    $default['dev']['uri'] = $dev;
    $default['test']['uri'] = $test;
    $default['prod']['uri'] = $host;

    $this->taskWriteToFile("{$root}/drush/sites/{$id}.site.yml")
      ->text(Yaml::dump($default, 10, 2))
      ->run();

    $this->say("Updated <comment>{$id}.site.yml</comment> Drush alias file with <info>local, dev, test and prod</info> aliases.");

    // Overwrite the multisite blt.yml file.
    $blt = Yaml::parse(file_get_contents("{$root}/docroot/sites/{$host}/blt.yml"));
    $blt['project']['machine_name'] = $id;
    $blt['project']['local']['hostname'] = $local;
    $blt['drupal']['db']['database'] = $db;
    $blt['drush']['aliases']['local'] = 'self';
    $blt['uiowa']['profiles']['sitenow']['requester'] = $requester;

    $this->taskWriteToFile("{$root}/docroot/sites/{$host}/blt.yml")
      ->text(Yaml::dump($blt, 10, 2))
      ->run();

    $this->say("Overwrote <comment>docroot/sites/{$host}/blt.yml</comment> file with standardized names.");

    // Write sites.php data. Note that we exclude production URI since it will
    // route automatically.
    $data = <<<EOD

// Directory aliases for {$host}.
\$sites['{$local}'] = '{$host}';
\$sites['{$dev}'] = '{$host}';
\$sites['{$test}'] = '{$host}';
\$sites['{$prod}'] = '{$host}';

EOD;

    $this->taskWriteToFile("{$root}/docroot/sites/sites.php")
      ->text($data)
      ->append()
      ->run();

    $this->say('Added default <comment>sites.php</comment> entries.');

    // Regenerate the local settings file - it had the wrong database name.
    $this->invokeCommand('blt:init:settings');

    // Create the config directory with a file to commit.
    $this->taskFilesystemStack()
      ->mkdir("{$root}/config/{$host}")
      ->touch("{$root}/config/{$host}/.gitkeep")
      ->run();

    $this->taskGit()
      ->dir($root)
      ->add('box/config.yml')
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
   * Return new Client for interacting with Acquia Cloud API.
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
