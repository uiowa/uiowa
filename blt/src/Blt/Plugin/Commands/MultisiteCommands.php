<?php

namespace Sitenow\Blt\Plugin\Commands;

use Acquia\Blt\Robo\BltTasks;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\CommandError;
use Symfony\Component\Yaml\Yaml;

/**
 * Defines commands in the Sitenow namespace.
 */
class MultisiteCommands extends BltTasks {

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
        $commandData->input()->setOption('remote-alias', "{$machineName}.dev");
        $this->getConfig()->set('drupal.db.database', $machineName);

      }
      else {
        return new CommandError('Cannot parse URI for validation.');
      }
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

    // Re-generate the Drush alias so it is more useful.
    $file = "{$root}/drush/sites/{$dir}.site.yml";
    if (file_exists($file)) {
      unlink("{$root}/drush/sites/{$dir}.site.yml");
    }

    $app = $this->getConfig()->get('project.prefix');
    $default = Yaml::parse(file_get_contents("{$root}/drush/sites/{$app}.site.yml"));
    $default['prod']['uri'] = $dir;
    $default['test']['uri'] = $test;
    $default['dev']['uri'] = $dev;

    file_put_contents("{$root}/drush/sites/{$machineName}.site.yml", Yaml::dump($default, 10, 2));
    $this->say("Created <comment>{$machineName}.site.yml</comment> Drush alias file.");

    // Overwrite the multisite blt.yml file.
    $blt = Yaml::parse(file_get_contents("{$root}/docroot/sites/{$dir}/blt.yml"));
    $blt['project']['machine_name'] = $machineName;
    $blt['drupal']['db']['database'] = $db;
    file_put_contents("{$root}/docroot/sites/{$dir}/blt.yml", Yaml::dump($blt, 10, 2));
    $this->say("Overwrote <comment>{$root}/docroot/sites/{$dir}/blt.yml</comment> file.");

    // Write sites.php data.
    $data = <<<EOD

// Directory aliases for {$dir}.
\$sites['{$dev}'] = '{$dir}';
\$sites['{$test}'] = '{$dir}';
\$sites['{$machineName}.prod.drupal.uiowa.edu'] = '{$dir}';

EOD;

    file_put_contents($root . '/docroot/sites/sites.php', $data, FILE_APPEND);
    $this->say('Added <comment>sites.php</comment> entries. Adjust as needed. Create domains in the Acquia Cloud UI.');

    // Remove the new local settings file - it has the wrong database name.
    $file = "{$root}/docroot/sites/{$dir}/settings/local.settings.php";

    if (file_exists($file)) {
      unlink($file);
    }

    $this->invokeCommand('blt:init:settings');
    $this->say('<comment>Multisite initialization complete</comment>. Diff and commit code changes to a new feature branch. Install the site locally by running the below Drush command.');
    $this->yell("drush site:install sitenow --sites-subdir {$dir} --existing-config");
    $this->say("Acquia Cloud database must be named <comment>{$db}</comment>. Create in the Cloud UI.");
  }

  /**
   * Zero out the sites.local.php file as this seems to mess with BLT.
   *
   * @hook pre-command drupal:sync:all-sites
   */
  public function preSync(CommandData $commandData) {
    $root = $this->getConfigValue('repo.root');
    file_put_contents("{$root}/docroot/sites/sites.local.php", "<?php\n");
    $this->yell('The sites.local.php file has been emptied. Restart Drush runserver after sync is complete.');
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
