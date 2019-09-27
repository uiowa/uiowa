<?php

namespace Sitenow\Blt\Plugin\Commands;

use Acquia\Blt\Robo\BltTasks;
use AcquiaCloudApi\CloudApi\Client;
use AcquiaCloudApi\CloudApi\Connector;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\CommandError;
use Drush\Exceptions\UserAbortException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;
use Sitenow\Multisite;

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
    $root = $this->getConfigValue("repo.root");

    $finder = new Finder();

    $dirs = $finder
      ->in("{$root}/docroot/sites/")
      ->directories()
      ->depth('< 1')
      ->exclude(['default', 'g', 'settings'])
      ->sortByName();

    $sites = [];
    foreach ($dirs->getIterator() as $dir) {
      $sites[] = $dir->getRelativePathname();
    }

    $dir = $this->askChoice('Select which site to delete.', $sites);
    $db = Multisite::getDatabase($dir);
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
      throw new UserAbortException();
    }
    else {
      $connector = new Connector([
        'key' => $this->getConfigValue('credentials.acquia.key'),
        'secret' => $this->getConfigValue('credentials.acquia.secret'),
      ]);

      $cloud = Client::factory($connector);

      $application = $cloud->application($this->getConfigValue('cloud.appId'));
      $databases = $cloud->databases($application->uuid);

      foreach ($databases as $database) {
        if ($database->name == $db) {
          $cloud->databaseDelete($application->uuid, $db);
          $this->say("Deleted <comment>{$db}</comment> cloud database.");
        }
      }

      foreach ($cloud->environments($application->uuid) as $environment) {
        if ($intersect = array_intersect($properties['domains'], $environment->domains)) {
          foreach ($intersect as $domain) {
            $cloud->deleteDomain($environment->uuid, $domain);
            $this->say("Deleted <comment>{$domain}</comment> cloud domain.");
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
        ->commit("Deleted sites.php entries for {$dir}")
        ->add("docroot/sites/{$dir}/")
        ->commit("Deleted multisite {$dir} directory")
        ->add("drush/sites/{$id}.site.yml")
        ->commit("Deleted Drush aliases for {$dir}")
        ->add("config/{$dir}/")
        ->commit("Deleted config directory for {$dir}")
        ->interactive(FALSE)
        ->printOutput(FALSE)
        ->printMetadata(FALSE)
        ->run();

      $this->say("Committed deletion of site <comment>{$dir}</comment> code.");
      $this->say("Continue deleting additional multisites or push this branch and merge via a pull request. Immediate production release not necessary.");
    }
  }

  /**
   * Require the --site-uri option so it can be used in postMultisiteInit.
   *
   * @hook validate recipes:multisite:init
   *
   * @requireFeatureBranch
   * @requireCredentials
   */
  public function validateMultisiteInit(CommandData $commandData) {
    $uri = $commandData->input()->getOption('site-uri');

    if (!$uri) {
      return new CommandError('Sitenow: you must supply the site URI via the --site-uri option.');
    }
    else {
      if (parse_url($uri, PHP_URL_SCHEME) == NULL) {
        $uri = "https://{$uri}";
      }

      if ($parsed = parse_url($uri)) {
        if (isset($parsed['path'])) {
          return new CommandError('Sitenow: Subdirectory sites are not supported.');
        }

        $commandData->input()->setOption('site-uri', $uri);
        $commandData->input()->setOption('site-dir', $parsed['host']);
        $id = Multisite::getIdentifier($uri);
        $commandData->input()->setOption('remote-alias', "{$id}.prod");
        $this->getConfig()->set('drupal.db.database', $parsed['host']);

      }
      else {
        return new CommandError('Cannot parse URI for validation.');
      }
    }

    if (!$commandData->input()->getOption('no-interaction')) {
      return new CommandError("You must specify the --no-interaction option.");
    }
  }

  /**
   * This will be called after the `recipes:multisite:init` command is executed.
   *
   * @hook post-command recipes:multisite:init
   */
  public function postMultisiteInit($result, CommandData $commandData) {
    $uri = $commandData->input()->getOption('site-uri');
    $dir = $commandData->input()->getOption('site-dir');

    $db = Multisite::getDatabase($dir);
    $id = Multisite::getIdentifier($uri);
    $dev = Multisite::getInternalDomains($id)['dev'];
    $test = Multisite::getInternalDomains($id)['test'];
    $prod = Multisite::getInternalDomains($id)['prod'];

    $root = $this->getConfigValue('repo.root');

    // Remove some files that we probably don't need.
    $files = [
      "{$root}/docroot/sites/{$dir}/default.services.yml",
      "{$root}/docroot/sites/{$dir}/services.yml",
      "{$root}/drush/sites/{$dir}.site.yml",
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
    $default['local']['uri'] = $dir;
    $default['dev']['uri'] = $dev;
    $default['test']['uri'] = $test;
    $default['prod']['uri'] = $dir;

    file_put_contents("{$root}/drush/sites/{$id}.site.yml", Yaml::dump($default, 10, 2));
    $this->say("Updated <comment>{$id}.site.yml</comment> Drush alias file with <info>local, dev, test and prod</info> aliases.");

    // Overwrite the multisite blt.yml file.
    $blt = Yaml::parse(file_get_contents("{$root}/docroot/sites/{$dir}/blt.yml"));
    $blt['project']['machine_name'] = $id;
    $blt['drupal']['db']['database'] = $db;
    $blt['drush']['aliases']['local'] = 'self';
    file_put_contents("{$root}/docroot/sites/{$dir}/blt.yml", Yaml::dump($blt, 10, 2));
    $this->say("Overwrote <comment>docroot/sites/{$dir}/blt.yml</comment> file with standardized names.");

    // Write sites.php data.
    $data = <<<EOD

// Directory aliases for {$dir}.
\$sites['{$dev}'] = '{$dir}';
\$sites['{$test}'] = '{$dir}';
\$sites['{$prod}'] = '{$dir}';

EOD;

    file_put_contents($root . '/docroot/sites/sites.php', $data, FILE_APPEND);
    $this->say('Added default <comment>sites.php</comment> entries.');

    // Remove the new local settings file - it has the wrong database name.
    $file = "{$root}/docroot/sites/{$dir}/settings/local.settings.php";

    if (file_exists($file)) {
      unlink($file);
    }

    $this->invokeCommand('blt:init:settings');

    // Create the config directory with a file to commit.
    $this->taskFilesystemStack()
      ->mkdir("{$root}/config/{$dir}")
      ->touch("{$root}/config/{$dir}/.gitkeep")
      ->run();

    $this->taskGit()
      ->dir($this->getConfigValue("repo.root"))
      ->add('docroot/sites/sites.php')
      ->commit("Add sites.php entries for {$dir}")
      ->add("docroot/sites/{$dir}")
      ->commit("Initialize multisite {$dir} directory")
      ->add("drush/sites/{$id}.site.yml")
      ->commit("Create Drush aliases for {$dir}")
      ->add("config/{$dir}")
      ->commit("Create config directory for {$dir}")
      ->interactive(FALSE)
      ->printOutput(FALSE)
      ->printMetadata(FALSE)
      ->run();

    $this->say("Committed site <comment>{$dir}</comment> code.");

    $connector = new Connector([
      'key' => $this->getConfigValue('credentials.acquia.key'),
      'secret' => $this->getConfigValue('credentials.acquia.secret'),
    ]);

    $cloud = Client::factory($connector);

    $application = $cloud->application($this->getConfigValue('cloud.appId'));
    $cloud->databaseCreate($application->uuid, $db);
    $this->say("Created <comment>{$db}</comment> cloud database.");

    $this->say("Continue initializing additional multisites or follow the next steps below.");

    $steps = [
      'Push this branch and merge via a pull request.',
      'Coordinate a new release and deploy to the test and prod environments.',
      'Add the multisite domains to environments as needed.',
      'Add the webmaster account(s) to the production site.',
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
      'credentials.acquia.key',
      'credentials.acquia.secret',
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

}
