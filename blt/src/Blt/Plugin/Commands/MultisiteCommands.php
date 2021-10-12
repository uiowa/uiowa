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
use AcquiaCloudApi\Exception\ApiErrorException;
use AcquiaCloudApi\Response\NotificationResponse;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\CommandError;
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
      $app = EnvironmentDetector::getAhGroup() ?: 'local';
      $env = EnvironmentDetector::getAhEnv() ?: 'local';

      $this->sendNotification("Command `drush {$cmd}` *started* on {$app} {$env}.");

      foreach ($this->getConfigValue('multisites') as $multisite) {
        $this->switchSiteContext($multisite);
        $db = $this->getConfigValue('drupal.db.database');

        // Skip sites whose database do not exist on the application in AH env.
        if (EnvironmentDetector::isAhEnv() && !file_exists("/var/www/site-php/{$app}/{$db}-settings.inc")) {
          $this->logger->info("Skipping {$multisite}. Database {$db} does not exist on this application.");
          continue;
        }

        if (!in_array($multisite, $options['exclude'])) {
          $this->say("<info>Executing on {$multisite}...</info>");

          // Define a site-specific cache directory.
          // @see: https://github.com/drush-ops/drush/pull/4345
          $tmp = "/tmp/.drush-cache-{$app}/{$env}/{$multisite}";

          $this->taskDrush()
            ->drush($cmd)
            ->option('define', "drush.paths.cache-directory={$tmp}")
            ->printMetadata(FALSE)
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

      if (!$this->getInspector()->isDrupalInstalled()) {
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
   *   Simulate BLT operations.
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

    $table->setHeaders([
      'Application',
      'DBs',
      'SANs',
      'SSL - Coverage',
      'SSL - Related Domain',
    ]);

    $rows = [];

    // A boolean to track whether any application covers this domain.
    $has_ssl_coverage = FALSE;
    $sans_search = Multisite::getSslParts($host)['sans'];
    $related_search = Multisite::getSslParts($host)['related'];

    foreach ($applications as $name => $uuid) {
      $row = [
        'app' => $name,
        'dbs' => count($databases->getAll($uuid)),
        'sans' => NULL,
        'ssl' => NULL,
        'related' => NULL,
      ];

      $envs = $environments->getAll($uuid);

      foreach ($envs as $env) {
        if ($env->name == 'prod') {
          $certs = $certificates->getAll($env->uuid);

          foreach ($certs as $cert) {
            if ($cert->flags->active == TRUE) {
              $row['sans'] = count($cert->domains);

              if ($sans_search) {
                foreach ($cert->domains as $domain) {
                  if ($domain == $sans_search) {
                    $row['ssl'] = $domain;
                    $has_ssl_coverage = TRUE;
                    break;
                  }

                  if ($domain == $related_search) {
                    $row['related'] = $domain;
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
      $this->logger->error("No SSL coverage found on any application for {$host}. Be sure to check existing SSL certificates for related domains and install a new one before updating DNS.");
    }

    $app = $this->askChoice('Which cloud application should be used?', array_keys($applications));

    // Get confirmation before executing.
    if (!$this->confirm("Selected {$app} application. Proceed?")) {
      throw new \Exception('Aborted.');
    }

    // Get the UUID for the selected application.
    $app_id = $applications[$app];

    if (!$options['no-db']) {
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
      "{$root}/config/{$host}",
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

    // Initialize next steps.
    $steps = [];

    if (!$options['no-commit']) {
      $this->taskGit()
        ->dir($root)
        ->add('box/config.yml')
        ->add('docroot/sites/sites.php')
        ->add("docroot/sites/{$host}")
        ->add("drush/sites/{$id}.site.yml")
        ->commit("Initialize {$host} multisite on {$app}")
        ->interactive(FALSE)
        ->printOutput(FALSE)
        ->printMetadata(FALSE)
        ->run();

      $result = $this->taskGit()
        ->dir($this->getConfigValue('repo.root'))
        ->exec('git rev-parse --abbrev-ref HEAD')
        ->interactive(FALSE)
        ->printOutput(FALSE)
        ->printMetadata(FALSE)
        ->run();

      $branch = $result->getMessage();

      $steps = [
        0 => "Push this branch and merge via a pull request: <comment>git push --set-upstream origin {$branch}</comment>",
        1 => 'Coordinate a new release and deploy to the test and prod environments.',
      ];
    }

    $this->say("Committed site <comment>{$host}</comment> code.");
    $this->say("Continue initializing additional multisites or follow the next steps below.");

    $steps += [
      1 => 'Deploy a release to production as per usual.',
      2 => 'Once deployed, invoke the <comment>uiowa:multisite:install</comment> BLT command in the production environment on the appropriate application(s)',
      3 => 'Add the multisite domains to environments as needed.',
    ];

    $this->io()->listing($steps);
  }

  /**
   * Transfer a multisite from one application to another.
   *
   * @option no-commit
   *   Do not commit the code changes.
   * @option test-mode
   *  Test mode will still sync a site but will use the test environment.
   *
   * @command uiowa:multisite:transfer
   *
   * @aliases umt
   *
   * @requireFeatureBranch
   * @requireCredentials
   *
   * @throws \Exception
   */
  public function transfer($options = [
    'no-commit' => FALSE,
    'test-mode' => FALSE,
  ]) {
    $root = $this->getConfigValue('repo.root');
    $sites = Multisite::getAllSites($root);
    $site = $this->askChoice('Select which site to transfer.', $sites);
    $id = Multisite::getIdentifier("https://$site");

    $mode = $options['test-mode'] ? 'test' : 'prod';

    $result = $this->taskDrush()
      ->alias("$id.$mode")
      ->drush('status')
      ->options([
        'field' => 'application',
      ])
      ->printMetadata(FALSE)
      ->printOutput(FALSE)
      ->run();

    if (!$result->wasSuccessful()) {
      return new CommandError('Unable to get current application with Drush.');
    }

    $old = trim($result->getMessage());

    // Get the applications and unset the current as an option.
    $applications = $this->config->get('uiowa.applications');
    $choices = $applications;
    unset($choices[$old]);
    $new = $this->askChoice("Site $site is currently on $old. Which cloud application should it be transferred to?", array_keys($choices));

    $client = $this->getAcquiaCloudApiClient();
    $certificates = new SslCertificates($client);

    // Check that new application has SSL coverage.
    $client->addQuery('filter', "name=$mode");
    $response = $client->request('GET', "/applications/$applications[$new]/environments");
    $client->clearQuery();

    // If for some odd reason there is more than one environment, bail.
    if (!$response || count($response) > 1) {
      return new CommandError("Error getting information for new application $mode environment.");
    }

    /** @var object $target_env */
    $target_env = array_shift($response);

    if ($mode == 'prod') {
      $this->logger->notice('Checking SSL coverage...');
      $has_ssl_coverage = FALSE;
      $certs = $certificates->getAll($target_env->id);
      $sans_search = Multisite::getSslParts($site)['sans'];

      foreach ($certs as $cert) {
        if ($cert->flags->active == TRUE) {
          foreach ($cert->domains as $san) {
            if ($san == $site || $san == $sans_search) {
              $has_ssl_coverage = TRUE;
              break 2;
            }
          }
        }
      }

      if (!$has_ssl_coverage) {
        return new CommandError("No SSL coverage for $site on $new.");
      }
    }
    else {
      $this->logger->info('Skipping SSL check in test mode.');
    }

    // Make the user confirm before proceeding.
    if (!$this->confirm("You will transfer $site from $old $mode -> local -> $new $mode. Are you sure?", TRUE)) {
      throw new \Exception('Aborted.');
    }

    $databases = new Databases($client);
    $db = Multisite::getDatabaseName($site);
    $this->logger->notice("Starting cloud database creation for <comment>{$db}</comment> database on $new...");
    $database_op = $databases->create($applications[$new], $db);

    // Make sure the database exists locally by just recreating it.
    $this->taskDrush()
      ->alias("$id.local")
      ->drush('sql:create')
      ->stopOnFail()
      ->run();

    $this->taskDrush()
      ->drush('sql:sync')
      ->args([
        "@$id.$mode",
        "@$id.local",
      ])
      ->stopOnFail()
      ->run();

    $this->taskDrush()
      ->drush('rsync')
      ->args([
        "@$id.$mode:%files",
        "@$id.local:%files",
      ])
      ->stopOnFail()
      ->run();

    // Now that the site is synced locally, change the Drush alias.
    $new_app_alias = YamlMunge::parseFile("{$root}/drush/sites/{$new}.site.yml");
    $site_alias = YamlMunge::parseFile("{$root}/drush/sites/{$id}.site.yml");

    // The local alias does not need any changes.
    foreach (['dev', 'test', 'prod'] as $env) {
      $site_alias[$env]['host'] = $new_app_alias[$env]['host'];
      $site_alias[$env]['user'] = $new_app_alias[$env]['user'];
      $site_alias[$env]['root'] = $new_app_alias[$env]['root'];
    }

    $this->taskWriteToFile("{$root}/drush/sites/{$id}.site.yml")
      ->text(Yaml::dump($site_alias, 10, 2))
      ->run();

    if (!$options['no-commit']) {
      $this->taskGit()
        ->dir($root)
        ->add("drush/sites/$id.site.yml")
        ->commit("Update $site Drush alias to new application $new")
        ->interactive(FALSE)
        ->printOutput(FALSE)
        ->printMetadata(FALSE)
        ->run();
    }

    $notification = $this->waitForOperation($database_op, $client);

    if ($notification->status != 'completed') {
      return new CommandError('Database create operation did not complete.');
    }

    $this->taskDrush()
      ->drush('sql:sync')
      ->args([
        "@$id.local",
        "@$id.$mode",
      ])
      ->stopOnFail()
      ->run();

    $this->taskDrush()
      ->drush('rsync')
      ->args([
        "@$id.local:%files",
        "@$id.$mode:%files",
      ])
      ->stopOnFail()
      ->run();

    // Remove the domain from the old application and create on the new one.
    $domains = new Domains($client);

    // Get the old environment UUID.
    $client->addQuery('filter', "name=$mode");
    $response = $client->request('GET', "/applications/$applications[$old]/environments");
    $client->clearQuery();

    if (!$response || count($response) > 1) {
      return new CommandError('Unable to get old application environment information. Domain not transferred.');
    }
    else {
      $source_env = array_shift($response);

      // Try to delete the prod domain first, then the internal domain. If
      // neither is found, log a warning to indicate something is off here.
      try {
        $domain_op = $domains->delete($source_env->id, $site);
        $this->logger->notice("Removed $site domain from $old $mode.");
      } catch (ApiErrorException $e) {
        $internal = Multisite::getInternalDomains($id)[$mode];

        try {
          $domain_op = $domains->delete($source_env->id, $internal);
          $this->logger->notice("Removed $internal domain from $old $mode.");
        } catch (ApiErrorException $e) {
          $this->logger->warning("Could not delete $site or $internal domain from $old $mode.");
        }
      }

      if (isset($domain_op)) {
        $this->waitForOperation($domain_op, $client);
      }

      try {
        $domains->create($target_env->id, $site);
        $this->logger->notice("Created $site domain on $new $mode.");;
      } catch (ApiErrorException $e) {
        $this->logger->warning("Count not create $site domain on $new $mode.");
      }
    }

    // @todo Remove files and database.
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
    $env = EnvironmentDetector::getAhEnv() ?: 'local';
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
          ->args("config_split.config_split.{$split}", 'status', TRUE)
          ->drush('cache:rebuild')
          ->drush('config:import')
          ->run();
      }
    }
  }

  /**
   * Wait for a Cloud API operation to complete.
   *
   * @param \AcquiaCloudApi\Response\OperationResponse $operation
   * @param Client $client
   */
  protected function waitForOperation(\AcquiaCloudApi\Response\OperationResponse $operation, Client $client) {
    // Get the operation notification URL path and strip the leading 'api/'
    // from it because that is added below when making the request.
    $path = substr(parse_url($operation->links->notification->href, PHP_URL_PATH), 4);
    $this->logger->notice("Waiting for $operation->message to complete...");
    do {
      /** @var NotificationResponse $notification */
      $notification = $client->request('GET', $path);
      sleep(2);
    } while ($notification->status == 'in-progress');

    return $notification;
  }

}
