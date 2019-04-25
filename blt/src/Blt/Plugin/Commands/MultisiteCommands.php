<?php

namespace Sitenow\Blt\Plugin\Commands;

use Acquia\Blt\Robo\BltTasks;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\CommandError;

/**
 * Defines commands in the Sitenow namespace.
 */
class MultisiteCommands extends BltTasks {

  /**
   *Require the --site-uri option so it can be used in postMultisiteInit.
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
    $root = $this->getConfigValue('repo.root');

    $data = <<<EOD
    
// Directory aliases for {$uri}.
\$sites['{$machineName}.uiowa.lndo.site'] = '{$machineName}';
\$sites['{$machineName}.dev.drupal.uiowa.edu'] = '{$machineName}';
\$sites['{$machineName}.test.drupal.uiowa.edu'] = '{$machineName}';
\$sites['{$machineName}.prod.drupal.uiowa.edu'] = '{$machineName}';

EOD;

    file_put_contents($root . '/docroot/sites/sites.php', $data, FILE_APPEND);

    $this->say('Added <comment>sites.php</comment> entries. Adjust as needed and commit.');
  }

}
