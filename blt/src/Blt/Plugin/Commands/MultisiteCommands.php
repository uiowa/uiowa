<?php

namespace Sitenow\Blt\Plugin\Commands;

use Acquia\Blt\Robo\BltTasks;
use AcquiaCloudApi\CloudApi\Client;
use AcquiaCloudApi\CloudApi\Connector;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\CommandError;
use Symfony\Component\Yaml\Yaml;

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
   * Require the --site-uri option so it can be used in postMultisiteInit.
   *
   * @hook validate recipes:multisite:init
   */
  public function validateMultisiteInit(CommandData $commandData) {
    $creds = [
      'credentials.acquia.key',
      'credentials.acquia.secret',
    ];

    foreach ($creds as $cred) {
      if (!$this->getConfigValue($cred)) {
        return new CommandError("You must set {$cred} in your {$this->getConfigValue('repo.root')}/blt/local.blt.yml file. DO NOT commit these anywhere in the repository!");
      }
    }

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
        $machineName = $this->generateMachineName($uri);
        $commandData->input()->setOption('remote-alias', "{$machineName}.prod");
        $this->getConfig()->set('drupal.db.database', $machineName);

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
    $db = str_replace('.', '_', $dir);
    $machineName = $this->generateMachineName($uri);
    $dev = "{$machineName}.dev.drupal.uiowa.edu";
    $test = "{$machineName}.stage.drupal.uiowa.edu";
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
    $default['prod']['uri'] = $dir;
    $default['test']['uri'] = $test;
    $default['dev']['uri'] = $dev;

    file_put_contents("{$root}/drush/sites/{$machineName}.site.yml", Yaml::dump($default, 10, 2));
    $this->say("Updated <comment>{$machineName}.site.yml</comment> Drush alias file with <info>local, dev, test and prod</info> aliases.");

    // Overwrite the multisite blt.yml file.
    $blt = Yaml::parse(file_get_contents("{$root}/docroot/sites/{$dir}/blt.yml"));
    $blt['project']['machine_name'] = $machineName;
    $blt['drupal']['db']['database'] = $db;
    $blt['drush']['aliases']['local'] = 'self';
    file_put_contents("{$root}/docroot/sites/{$dir}/blt.yml", Yaml::dump($blt, 10, 2));
    $this->say("Overwrote <comment>docroot/sites/{$dir}/blt.yml</comment> file with standardized names.");

    // Write sites.php data.
    $data = <<<EOD

// Directory aliases for {$dir}.
\$sites['{$dev}'] = '{$dir}';
\$sites['{$test}'] = '{$dir}';
\$sites['{$machineName}.prod.drupal.uiowa.edu'] = '{$dir}';

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

    // Site install locally so we can do some post-install tasks.
    // @see: https://www.drupal.org/project/drupal/issues/2982052
    $this->switchSiteContext($dir);

    $uid = uniqid('admin_');

    $this->taskDrush()
      ->drush('site:install')
      ->arg('sitenow')
      ->options([
        'sites-subdir' => $dir,
        'existing-config' => NULL,
        'account-name' => $uid,
        'account-mail' => base64_decode('aXRzLXdlYkB1aW93YS5lZHU='),
      ])
      ->run();

    $this->taskDrush()
      ->drush('user:role:add')
      ->args([
        'administrator',
        $uid,
      ])
      ->run();

    $this->taskDrush()
      ->drush('config:set')
      ->args([
        'system.site',
        'name',
        $dir,
      ])
      ->run();

    $branch = "initialize-{$dir}";

    $this->taskGit()
      ->dir($this->getConfigValue("repo.root"))
      ->exec("git checkout -b {$branch} master")
      ->add('docroot/sites/sites.php')
      ->commit("Add sites.php entries for {$dir}")
      ->add("docroot/sites/{$dir}")
      ->commit("Initialize multisite {$dir} directory")
      ->add("drush/sites/{$machineName}.site.yml")
      ->commit("Create Drush aliases for {$dir}")
      ->add("config/{$dir}")
      ->commit("Create config directory for {$dir}")
      ->exec("git push -u origin {$branch}")
      ->checkout('master')
      ->interactive(FALSE)
      ->printOutput(FALSE)
      ->printMetadata(FALSE)
      ->run();

    $connector = new Connector([
      'key' => $this->getConfigValue('credentials.acquia.key'),
      'secret' => $this->getConfigValue('credentials.acquia.secret'),
    ]);

    $cloud = Client::factory($connector);

    $application = $cloud->application($this->getConfigValue('cloud.appId'));
    $cloud->databaseCreate($application->uuid, $db);
    $this->say("Created <comment>{$db}</comment> cloud database.");

    $this->yell("Follow these next steps!");
    $steps = [
      "Open a PR at https://github.com/uiowa/{$app}/compare/master...{$branch}.",
      'Wait for the tests to pass, then merge the PR to trigger a Pipelines job.',
      'Check out and pull the master branch to update your local codebase.',
      'Wait for the Pipelines job to complete so that the code is deployed to the dev environment.',
      'Sync local database and files to dev environment.',
      'Rebuild the cache on the dev site.',
      'Re-deploy the pipelines-build-master branch to the dev environment in the Cloud UI. This will run the cloud hooks successfully.',
      'Sync the multisite database to the test and prod environments using the Cloud UI.',
      'Coordinate a new release to deploy to the test and prod environments.',
      'Clear the cache on the test and prod sites and re-deploy the tag artifacts.',
      'Sync the multisite files to the test and prod environments using Drush.',
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
   * Overwrite the sites.php file with no routed multisite.
   */
  protected function killServer() {
    $root = $this->getConfigValue('repo.root');
    file_put_contents("{$root}/docroot/sites/sites.local.php", "<?php\n");
    $this->getContainer()->get('executor')->killProcessByPort('8888');
    $this->yell('The sites.local.php file has been emptied. Runserver has been stopped.');
  }

  /**
   * Given a URI, create and return a unique ID.
   *
   * Used for internal subdomain and Drush alias group name, i.e. file name.
   *
   * @param string $uri
   *   The multisite URI.
   *
   * @return string
   *   The ID.
   */
  protected function generateMachineName($uri) {
    $parsed = parse_url($uri);

    if (substr($parsed['host'], -9) === 'uiowa.edu') {
      // Don't use the suffix if the host equals uiowa.edu.
      $machineName = substr($parsed['host'], 0, -10);

      // Reverse the subdomains.
      $parts = array_reverse(explode('.', $machineName));

      // Unset the www subdomain - considered the same site.
      $key = array_search('www', $parts);
      if ($key !== FALSE) {
        unset($parts[$key]);
      }
      $machineName = implode('', $parts);
    }
    else {
      // This site has a non-uiowa.edu TLD.
      $parts = explode('.', $parsed['host']);

      // Unset the www subdomain - considered the same site.
      $key = array_search('www', $parts);
      if ($key !== FALSE) {
        unset($parts[$key]);
      }

      // Pop off the suffix to be used later as a prefix.
      $extension = array_pop($parts);

      // Reverse the subdomains.
      $parts = array_reverse($parts);
      $machineName = $extension . '-' . implode('', $parts);
    }

    return $machineName;
  }

}
