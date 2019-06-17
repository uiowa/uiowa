<?php

namespace Example\Blt\Plugin\Commands;

use Acquia\Blt\Robo\BltTasks;
use Acquia\Blt\Robo\Exceptions\BltException;
use Consolidation\AnnotatedCommand\CommandError;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\InputOption;

define('LOCAL_BASE_DOMAIN', 'local.site');
define('UIOWA_BASE_DOMAIN', 'uiowa.edu');

/**
 * Defines commands in the "custom" namespace.
 */
class MultisiteCommands extends BltTasks {

  /**
   * Execute a Drush command against all multisites.
   *
   * @param string $cmd
   *   The simple Drush command to execute, e.g. 'cron' or 'cache:rebuild'. No
   *    support for options or arguments at this time.
   *
   * @command custom:multisite:drush
   *
   * @aliases cmd
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
   * Generates a new multisite.
   *
   * @command custom:multisite
   */
  public function generate($options = [
    'site-dir' => InputOption::VALUE_REQUIRED,
    'site-uri' => InputOption::VALUE_REQUIRED,
    'remote-alias' => InputOption::VALUE_REQUIRED,
  ]) {

    $this->say("This will generate a new site in the docroot/sites directory.");

    // 1. Get the production domain.
    $domain = $this->getNewSiteDomain($options);

    // 2. Turn the domain into a machine name.
    $machine_name = $this->generateMachineName($domain);

    // 3. Get and set the site directory.
    $new_site_dir = $this->getConfigValue('docroot') . '/sites/' . $this->getNewSiteDir($options, $domain);

    // @todo Decide whether it is important to include this here, even though it is redundant.
    if (file_exists($new_site_dir)) {
      throw new BltException("Cannot generate new multisite, $new_site_dir already exists!");
    }

    $options['site-dir'] = $new_site_dir;
    $options['site-uri'] = $domain;

    // @todo Set remote-alias

//    var_dump($options);

    // Pass these arguments to the 'recipes:multisite:init' command
    $this->invokeCommand('recipes:multisite:init', [$options]);
  }

  /**
   * @param $options
   * @param $site_name
   *
   * @return string
   */
  protected function getNewSiteDomain($options) {
    if (empty($options['site-uri'])) {
      $uri = $this->askRequired("Production domain or subdomain name (e.g. 'example', 'example.uiowa.edu' or 'uiowaexample.com')");
    }
    else {
      $uri = $options['site-uri'];
    }

    // Add the URL scheme if not supplied.
    if (parse_url($uri, PHP_URL_SCHEME) == NULL) {
      $uri = "https://{$uri}";
    }

    if ($parsed = parse_url($uri)) {
      // Don't allow subdirectory sites, such as uiowa.edu/example
      if (isset($parsed['path'])) {
        return new CommandError('Subdirectory sites are not supported.');
      }

      // If this is a string with no periods, we are assuming that it is meant to be a subdomain of uiowa.edu
      if (strpos($parsed['host'], '.') === FALSE) {
        $uri .= '.' . UIOWA_BASE_DOMAIN;
      }
    }
    else {
      return new CommandError('Cannot parse URI for validation.');
    }

    $this->say('>>> Domain will be: ' . $uri);

    return $uri;
  }

  protected function getNewSiteDir($options, $domain) {

    if (!empty($options['site-dir'])) {
      $dir = $options['site-dir'];
    }
    else {
      $parsed = parse_url($domain);

      // Suggest the supplied domain as the site directory
      if (isset($parsed['host'])) {
        $dir = $this->askDefault("Site directory",
          $parsed['host']);
      }
      else {
        $dir = $this->askRequired("Site directory (e.g. 'example')");
      }
    }

    return $dir;
  }

  /**
   * This will be called before the `custom:hello` command is executed.
   *
   * @hook command-event custom:hello
   */
  public function preExampleHello(ConsoleCommandEvent $event) {
    $command = $event->getCommand();
    $this->say("preCommandMessage hook: The {$command->getName()} command is about to run!");
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

  protected function endsWithDomain($uri, $domain) {
    $parsed = parse_url($uri);
    $len = strlen($domain);

    return substr($parsed['host'], -($len)) === $domain;
  }

}
