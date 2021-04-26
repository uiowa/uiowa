<?php

namespace Drupal\sitenow\Commands;

use Drush\Commands\DrushCommands;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 */
class SiteNowCommands extends DrushCommands {

  /**
   * Displays status of a valid configuration split.
   *
   * @command sitenow:config-split:status
   * @aliases scss
   *
   * @param string $split
   *   Argument provided to the drush command.
   *
   * @usage sitenow:config-split:status sitenow_v2
   */
  public function configSplitStatus(string $split) {

    /** @var \Drupal\Core\Plugin\DefaultPluginManager $filters */
    $filters = \Drupal::service('plugin.manager.config_filter')->getDefinitions();
    $split = 'config_split:' . $split;

    // If split isn't registered.
    if (!isset($filters[$split])) {
      \Drupal::logger('sitenow')->error('Split does not exist.');
      return;
    }
    $status = $filters[$split]["status"] ? 'true':'false';
    $this->output()->writeln($split . ':' . $status);
  }
}
