<?php

namespace Drupal\uiowa_core\Commands;

use Drush\Commands\DrushCommands;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 */
class UiowaCoreCommands extends DrushCommands {

  /**
   * Toggles Site-Specific Google Tag inserts.
   *
   * @command uiowa_core:toggle-gtag
   * @aliases uicore-gtag
   */
  public function toggleGtag() {
    $config_factory = \Drupal::configFactory();
    $config = $config_factory->getEditable('uiowa_core.settings');
    $uiowa_core_gtag = $config->get('uiowa_core.gtag');

    if ($uiowa_core_gtag == '1') {
      \Drupal::logger('uiowa_core')
        ->notice('Site-specific Google Tag Manager Disabled');
      $config
        ->set('uiowa_core.gtag', '0')
        ->save();
    }
    else {
      \Drupal::logger('uiowa_core')
        ->notice('Site-specific Google Tag Manager Enabled');
      $config
        ->set('uiowa_core.gtag', '1')
        ->save();
    }
    // Flush site cache.
    drupal_flush_all_caches();

    // If available (not Local), try to clear the varnish cache for the files.
    if (\Drupal::moduleHandler()->moduleExists('purge')) {
      $purgeInvalidationFactory = \Drupal::service('purge.invalidation.factory');
      $purgeQueuers = \Drupal::service('purge.queuers');
      $purgeQueue = \Drupal::service('purge.queue');
      $queuer = $purgeQueuers->get('coretags');
      $invalidations = [
        $purgeInvalidationFactory->get('everything'),
      ];
      $purgeQueue->add($queuer, $invalidations);
    }
  }

}
