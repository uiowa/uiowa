<?php

namespace Collegiate\Blt\Plugin\Commands;

use Acquia\Blt\Robo\BltTasks;
use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\CommandError;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Defines commands in the Collegiate namespace.
 */
class MultisiteCommands extends BltTasks {

  /**
   * Execute a Drush command against all multisites.
   *
   * @param string $cmd
   *   The simple Drush command to execute, e.g. 'cron' or 'cache:rebuild'. No
   *    support for options or arguments at this time.
   *
   * @command recipes:multisite:execute
   *
   * @aliases rme
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
   * Execute the collegiate multisite command.
   *
   * @hook init collegiate:multisite
   */
  public function initMultisite(InputInterface $input, AnnotationData $annotationData) {
    $value = $input->getOption('site-uri');
    $dir = $input->getOption('site-dir');
    if (!$value) {
      $input->setOption('site-uri', "https://$dir.uiowa.edu");
    }
  }

  /**
   * Require the --site-uri option so it can be used in postMultisiteInit.
   *
   * @hook validate recipes:multisite:init
   */
  public function validateMultisiteInit(CommandData $commandData) {
    if (!$commandData->input()->getOption('site-uri')) {
      return new CommandError('Sitenow: you must supply the site URI via the --site-uri option.');
    }

    if (!$commandData->input()->getOption('site-dir')) {
      return new CommandError('Sitenow: you must supply the machine name via the --site-dir option.');
    }
  }

  /**
   * This will be called after the `recipes:multisite:init` command is executed.
   *
   * @hook post-command recipes:multisite:init
   */
  public function postMultisiteInit($result, CommandData $commandData) {
    $uri = $commandData->input()->getOption('site-uri');
    $machineName = $commandData->input()->getOption('site-dir');
    $local = "{$machineName}.uiowa.local.site";
    $dev = "{machineName}.dev.drupal.uiowa.edu";
    $test = "{$machineName}.stage.drupal.uiowa.edu";
    $prod = "{$machineName}.prod.drupal.uiowa.edu";
    $root = $this->getConfigValue('repo.root');

    $data = <<<EOD

// Directory aliases for {$uri}.
\$sites['{$local}'] = '{$machineName}';
\$sites['{$dev}'] = '{$machineName}';
\$sites['{$test}'] = '{$machineName}';
\$sites['{$prod}'] = '{$machineName}';

EOD;

    file_put_contents($root . '/docroot/sites/sites.php', $data, FILE_APPEND);
    $this->say('Added default <comment>sites.php</comment> entries.');

    $this->say('Added <comment>sites.php</comment> entries. Adjust as needed and commit.');
  }

}
