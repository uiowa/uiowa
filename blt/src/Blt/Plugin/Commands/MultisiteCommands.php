<?php

namespace Uiowa\Blt\Plugin\Commands;

use Acquia\Blt\Robo\BltTasks;
use Acquia\Blt\Robo\Common\EnvironmentDetector;
use Acquia\Blt\Robo\Common\YamlMunge;
use Acquia\Blt\Robo\Exceptions\BltException;
use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Connector\Connector;
use AcquiaCloudApi\Endpoints\Databases;
use AcquiaCloudApi\Endpoints\Domains;
use AcquiaCloudApi\Endpoints\Environments;
use AcquiaCloudApi\Endpoints\SslCertificates;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\CommandError;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Yaml\Yaml;
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
   *
   * @hidden
   */
  public function noop() {

  }

  /**
   * Run cron via a request to the site's cron URL.
   *
   * We use this approach to avoid Drush cache clear collisions as well as to
   * provide more visibility into cron task warnings and errors in the logs.
   *
   * @command uiowa:multisite:cron
   *
   * @aliases umcron
   */
  public function cron() {
    if (!$this->confirm("You will execute cron on all multisites. Are you sure?", TRUE)) {
      throw new \Exception('Aborted.');
    }
    else {
      $app = EnvironmentDetector::getAhGroup() ? EnvironmentDetector::getAhGroup() : 'local';
      $env = EnvironmentDetector::getAhEnv() ? EnvironmentDetector::getAhEnv() : 'local';

      foreach ($this->getConfigValue('multisites') as $multisite) {
        $this->switchSiteContext($multisite);
        $db = $this->getConfigValue('drupal.db.database');

        // Skip sites whose database do not exist on the application in AH env.
        if (EnvironmentDetector::isAhEnv() && !file_exists("/var/www/site-php/{$app}/{$db}-settings.inc")) {
          $this->say("Skipping {$multisite}. Database {$db} does not exist.");
          continue;
        }

        // Skip sites that are not installed since we cannot retrieve state.
        if (!$this->getInspector()->isDrupalInstalled()) {
          continue;
        }

        // Define a site-specific cache directory.
        // @see: https://github.com/drush-ops/drush/pull/4345
        $tmp = "/tmp/.drush-cache-{$app}/{$env}/{$multisite}";

        $result = $this->taskDrush()
          ->drush('state:get')
          ->arg('system.cron_key')
          ->option('define', "drush.paths.cache-directory={$tmp}")
          ->run();

        $cron_key = trim($result->getMessage());

        $id = Multisite::getIdentifier("//{$multisite}");
        $domain = Multisite::getInternalDomains($id)[$env];

        // Don't verify self-signed SSL certificate in the local environment.
        $client = new GuzzleClient([
          'verify' => ($app == 'local') ? FALSE : TRUE,
        ]);

        try {
          $client->get("https://{$domain}/cron/{$cron_key}");
        }
        catch (RequestException $e) {
          if ($env == 'prod') {
            try {
              $client->get("https://{$multisite}/cron/{$cron_key}");
            }
            catch (RequestException $e) {
              $message = $e->getMessage();
              $this->logger->error("Cannot run cron for site {$multisite}: {$message}.");
            }
          }
          else {
            $message = $e->getMessage();
            $this->logger->error("Cannot run cron for site {$domain}: {$message}.");
          }
        }

      }
    }
  }

  /**
   * Execute a Drush command against all multisites.
   *
   * @param string $cmd
   *   The simple Drush command to execute, e.g. 'cron' or 'cache:rebuild'. No
   *    support for options or arguments at this time.
   * @param array $options
   *   Array of options.
   *
   * @option exclude
   *   Sites to exclude from command execution.
   *
   * @command uiowa:multisite:execute
   *
   * @aliases ume
   *
   * @throws \Exception
   */
  public function execute($cmd, array $options = ['exclude' => []]) {
    if (!$this->confirm("You will execute 'drush {$cmd}' on all multisites. Are you sure?", TRUE)) {
      throw new \Exception('Aborted.');
    }
    else {
      $app = EnvironmentDetector::getAhGroup() ? EnvironmentDetector::getAhGroup() : 'local';
      $env = EnvironmentDetector::getAhEnv() ? EnvironmentDetector::getAhEnv() : 'local';

      $this->sendNotification("Command `drush {$cmd}` *started* on {$app} {$env}.");

      foreach ($this->getConfigValue('multisites') as $multisite) {
        $this->switchSiteContext($multisite);
        $db = $this->getConfigValue('drupal.db.database');

        // Skip sites whose database do not exist on the application in AH env.
        if (EnvironmentDetector::isAhEnv() && !file_exists("/var/www/site-php/{$app}/{$db}-settings.inc")) {
          $this->say("Skipping {$multisite}. Database {$db} does not exist.");
          continue;
        }

        if (!in_array($multisite, $options['exclude'])) {
          // Define a site-specific cache directory.
          // @see: https://github.com/drush-ops/drush/pull/4345
          $tmp = "/tmp/.drush-cache-{$app}/{$env}/{$multisite}";

          $this->taskDrush()
            ->drush($cmd)
            ->option('define', "drush.paths.cache-directory={$tmp}")
            ->run();
        }
        else {
          $this->logger->info("Skipping excluded site {$multisite}.");
        }
      }

      $this->sendNotification("Command `drush {$cmd}` *finished* on {$app} {$env}.");
    }
  }

  /**
   * Invoke the BLT install process on multisites where Drupal is not installed.
   *
   * @param array $options
   *   Command options.
   * @option envs
   *   Array of allowed environments for installation to happen on.
   * @option dry-run
   *   Report back the uninstalled sites but do not install.
   *
   * @command uiowa:multisite:install
   *
   * @aliases umi
   *
   * @throws \Exception
   *
   * @return mixed
   *   CommandError, list of uninstalled sites or the output from installation.
   *
   * @see: Acquia\Blt\Robo\Commands\Drupal\InstallCommand
   */
  public function install(array $options = [
    'envs' => [
      'local',
      'prod',
    ],
    'dry-run' => FALSE,
  ]) {
    $app = EnvironmentDetector::getAhGroup() ? EnvironmentDetector::getAhGroup() : 'local';
    $env = EnvironmentDetector::getAhEnv() ? EnvironmentDetector::getAhEnv() : 'local';

    if (!in_array($env, $options['envs'])) {
      $allowed = implode(', ', $options['envs']);
      return new CommandError("Multisite installation not allowed on {$env} environment. Must be one of {$allowed}. Use option to override.");
    }

    $uninstalled = [];

    foreach ($this->getConfigValue('multisites') as $multisite) {
      $this->switchSiteContext($multisite);
      $db = $this->getConfigValue('drupal.db.database');

      // Skip sites whose database do not exist on the application in AH env.
      if (EnvironmentDetector::isAhEnv() && !file_exists("/var/www/site-php/{$app}/{$db}-settings.inc")) {
        $this->logger->info("Skipping {$multisite}. Database {$db} does not exist.");
        continue;
      }

      if (!$this->getInspector()->isDrupalInstalled()) {
        $uninstalled[] = $multisite;
      }
    }

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

            // The site name option used during drush site:install is
            // overwritten if installed from existing configuration.
            $this->taskDrush()
              ->stopOnFail(FALSE)
              ->drush('config:set')
              ->args([
                'system.site',
                'name',
                $multisite,
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
              $this->taskDrush()
                ->stopOnFail(FALSE)
                ->drush('config:set')
                ->args("config_split.config_split.{$split}", 'status', true)
                ->drush('cache:rebuild')
                ->drush('config:import')
                ->run();
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
   *   Simulate cloud operations and file system tasks.
   *
   * @command uiowa:multisite:delete
   *
   * @aliases umd
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

        foreach ($this->getConfigValue('uiowa.applications') as $name => $uuid) {
          /** @var \AcquiaCloudApi\Endpoints\Databases $databases */
          $databases = new Databases($client);

          // Find the application that hosts the database.
          foreach ($databases->getAll($uuid) as $database) {
            if ($database->name == $db) {
              $databases->delete($uuid, $db);
              $this->say("Deleted <comment>{$db}</comment> cloud database on <comment>{$name}</comment> application.");

              /** @var \AcquiaCloudApi\Endpoints\Environments $environments */
              $environments = new Environments($client);

              foreach ($environments->getAll($uuid) as $environment) {
                if ($intersect = array_intersect($properties['domains'], $environment->domains)) {
                  $domains = new Domains($client);

                  foreach ($intersect as $domain) {
                    $domains->delete($environment->uuid, $domain);
                    $this->say("Deleted <comment>{$domain}</comment> domain on {$name} application.");
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
        ->commit("Delete {$dir} multisite on {$name}")
        ->interactive(FALSE)
        ->printOutput(FALSE)
        ->printMetadata(FALSE)
        ->run();

      $this->say("Committed deletion of site <comment>{$dir}</comment> code.");
      $this->say("Continue deleting additional multisites or push this branch and merge via a pull request. Immediate production release not necessary.");
    }
  }

  /**
   * Validate uiowa:multisite:create command.
   *
   * @hook validate uiowa:multisite:create
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
        return new CommandError('Subdirectory sites are not supported.');
      }
    }
    else {
      return new CommandError('Cannot parse URI for validation.');
    }

    // RMI does this but we run it with no interaction.
    if (file_exists("{$root}/docroot/sites/{$host}")) {
      return new CommandError("Site {$host} already exists.");
    }
  }

  /**
   * Create multisite code, cloud database and domains.
   *
   * @param string $host
   *   The multisite URI host. Will be used as the site directory.
   * @param array $options
   *   An option that takes multiple values.
   *
   * @option simulate
   *   Simulate database creation and filesystem operations.
   * @option no-commit
   *   Do not create a git commit.
   * @option no-db
   *   Do not create a cloud database.
   * @option requester
   *   The HawkID of the original requester. Will be granted webmaster access.
   * @option split
   *   The name of a config split to activate and import after installation.
   *
   * @command uiowa:multisite:create
   *
   * @aliases umc
   *
   * @requireFeatureBranch
   * @requireCredentials
   *
   * @throws \Exception
   */
  public function create($host, array $options = [
    'simulate' => FALSE,
    'no-commit' => FALSE,
    'no-db' => FALSE,
    'requester' => InputOption::VALUE_REQUIRED,
    'split' => InputOption::VALUE_REQUIRED,
  ]) {
    $db = Multisite::getDatabaseName($host);
    $applications = $this->getConfigValue('uiowa.applications');
    $this->say('<comment>Note:</comment> Multisites should be grouped on applications by domain since SSL certificates are limited to ~100 SANs. Otherwise, the application with the least amount of databases should be used.');

    /** @var \AcquiaCloudApi\Connector\Client $client */
    $client = $this->getAcquiaCloudApiClient();

    /** @var \AcquiaCloudApi\Endpoints\Databases $databases */
    $databases = new Databases($client);

    /** @var \AcquiaCloudApi\Endpoints\Environments $environments */
    $environments = new Environments($client);

    /** @var \AcquiaCloudApi\Endpoints\SslCertificates $certificates */
    $certificates = new SslCertificates($client);

    $table = new Table($this->output);
    $table->setHeaders(['Application', 'DBs', 'SANs', 'SSL Coverage']);
    $rows = [];

    // A boolean to track whether any application covers this domain.
    $has_ssl_coverage = FALSE;

    // Explode by domain and limit to two parts. Search for wildcard coverage.
    // Ex. foo.bar.uiowa.edu -> search for *.bar.uiowa.edu.
    // Ex. foo.bar.baz.uiowa.edu -> search for *.bar.baz.uiowa.edu.
    $host_parts = explode('.', $host, 2);
    $sans_search = '*.' . $host_parts[1];

    // If the host is one subdomain off uiowa.edu or a vanity domain,
    // search for the host instead.
    // Ex. foo.uiowa.edu -> search for foo.uiowa.edu.
    // Ex. foo.com -> search for foo.com.
    if ($host_parts[1] == 'uiowa.edu' || !stristr($host_parts[1], '.')) {
      $sans_search = $host;
    }

    foreach ($applications as $name => $uuid) {
      $row = [];
      $row[] = $name;
      $row[] = count($databases->getAll($uuid));

      $envs = $environments->getAll($uuid);

      foreach ($envs as $env) {
        if ($env->name == 'prod') {
          $certs = $certificates->getAll($env->uuid);

          foreach ($certs as $cert) {
            if ($cert->flags->active == TRUE) {
              $row[] = count($cert->domains);

              if ($sans_search) {
                foreach ($cert->domains as $domain) {
                  if ($domain == $sans_search) {
                    $row[] = $domain;
                    $has_ssl_coverage = TRUE;
                    break;
                  }
                }
              }
            }
          }
        }
      }

      $rows[] = $row;
    }

    $table->setRows($rows);
    $table->render();

    // If we did not find any SSL coverage, log an error.
    if (!$has_ssl_coverage) {
      $this->logger->error("No SSL coverage found on any application for {$host}. Be sure to install new SSL certificate before updating DNS.");
    }

    $app = $this->askChoice('Which cloud application should be used?', array_keys($applications));

    // Get confirmation before executing.
    if (!$this->confirm("Selected {$app} application. Proceed?")) {
      throw new \Exception('Aborted.');
    }

    // Get the UUID for the selected application.
    $app_id = $applications[$app];

    if (!$options['simulate'] && !$options['no-db']) {
      $databases->create($app_id, $db);
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

    // BLT RMI quotes this string after rewriting the YAML.
    $this->taskReplaceInFile("{$root}/box/config.yml")
      ->from("php_xdebug_cli_disable: 'no'")
      ->to("php_xdebug_cli_disable: no")
      ->run();

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

    // Remove some files that we don't need or will be regenerated below.
    $files = [
      "{$root}/docroot/sites/{$host}/default.services.yml",
      "{$root}/docroot/sites/{$host}/services.yml",
      "{$root}/drush/sites/{$host}.site.yml",
      "{$root}/docroot/sites/{$host}/settings/local.settings.php",
    ];

    $this->taskFilesystemStack()
      ->remove($files)
      ->run();

    // Discard changes to the default Drush alias.
    $this->taskGit()
      ->dir($root)
      ->exec('git checkout -f drush/sites/default.site.yml')
      ->run();

    // Re-generate the Drush alias so it is more useful.
    $drush_alias = YamlMunge::parseFile("{$root}/drush/sites/{$app}.site.yml");
    $files_path = "sites/{$host}/files";

    $drush_alias['local']['uri'] = $local;
    $drush_alias['dev']['uri'] = $dev;
    $drush_alias['test']['uri'] = $test;
    $drush_alias['prod']['uri'] = $host;

    foreach (['local', 'dev', 'test', 'prod'] as $env) {
      $drush_alias[$env]['paths']['files'] = $files_path;
    }

    $this->taskWriteToFile("{$root}/drush/sites/{$id}.site.yml")
      ->text(Yaml::dump($drush_alias, 10, 2))
      ->run();

    $this->say("Updated <comment>{$id}.site.yml</comment> Drush alias file with <info>local, dev, test and prod</info> aliases.");

    // Overwrite the multisite blt.yml file.
    $blt = YamlMunge::parseFile("{$root}/docroot/sites/{$host}/blt.yml");
    $blt['project']['machine_name'] = $id;
    $blt['project']['local']['hostname'] = $local;
    $blt['drupal']['db']['database'] = $db;
    $blt['drush']['aliases']['local'] = 'self';

    // Add custom options to the site's BLT settings.
    if (isset($options['requester'])) {
      $blt['uiowa']['requester'] = $options['requester'];
    }

    if (isset($options['split'])) {
      $blt['uiowa']['config']['split'] = $options['split'];
    }

    $this->taskWriteToFile("{$root}/docroot/sites/{$host}/blt.yml")
      ->text(Yaml::dump($blt, 10, 2))
      ->run();

    // Switch site context before expanding file properties.
    $this->switchSiteContext($host);
    $this->getConfig()->expandFileProperties("{$root}/docroot/sites/{$host}/blt.yml");

    $this->say("Wrote <comment>docroot/sites/{$host}/blt.yml</comment> file.");

    // Write sites.php data. Note that we exclude the production URI since it
    // will route automatically.
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
    $this->getConfig()->set('multisites', [
      $host,
    ]);

    $this->invokeCommand('blt:init:settings', [
      '--site' => $host,
    ]);

    // Create the config directory with a file to commit.
    $this->taskFilesystemStack()
      ->mkdir("{$root}/config/{$host}")
      ->touch("{$root}/config/{$host}/.gitkeep")
      ->run();

    // Initialize next steps.
    $steps = [];

    if (!$options['no-commit']) {
      $this->taskGit()
        ->dir($root)
        ->add('box/config.yml')
        ->add('docroot/sites/sites.php')
        ->add("docroot/sites/{$host}")
        ->add("drush/sites/{$id}.site.yml")
        ->add("config/{$host}")
        ->commit("Initialize {$host} multisite on {$app}")
        ->interactive(FALSE)
        ->printOutput(FALSE)
        ->printMetadata(FALSE)
        ->run();
      $steps += [
        'Push this branch and merge via a pull request.',
        'Coordinate a new release and deploy to the test and prod environments.',
      ];
    }

    $this->say("Committed site <comment>{$host}</comment> code.");
    $this->say("Continue initializing additional multisites or follow the next steps below.");

    $steps += [
      'Deploy a release to production as per usual.',
      'Once deployed, invoke the uiowa:multisite:install BLT command in the production environment on the appropriate application(s)',
      'Add the multisite domains to environments as needed.',
    ];

    $this->io()->listing($steps);
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

  /**
   * Send a Slack notification if the webhook environment variable exists.
   *
   * @param string $message
   *   The message to send.
   */
  protected function sendNotification($message) {
    $env = EnvironmentDetector::getAhEnv() ? EnvironmentDetector::getAhEnv() : 'local';
    $webhook_url = getenv('SLACK_WEBHOOK_URL');

    if ($webhook_url && $env == 'prod' || $env == 'local') {
      $payload = [
        'username' => 'Acquia Cloud',
        'text' => $message,
        'icon_emoji' => ':acquia:',
      ];

      $data = "payload=" . json_encode($payload);
      $ch = curl_init($webhook_url);
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
      curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
      curl_exec($ch);
      curl_close($ch);
    }
    else {
      $this->logger->warning("Slack webhook URL not configured. Cannot send message: {$message}");
    }
  }

}
