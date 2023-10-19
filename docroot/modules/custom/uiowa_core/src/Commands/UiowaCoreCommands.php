<?php

namespace Drupal\uiowa_core\Commands;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\purge\Plugin\Purge\Invalidation\InvalidationsService;
use Drupal\purge\Plugin\Purge\Queue\QueueService;
use Drupal\purge\Plugin\Purge\Queuer\QueuersService;
use Drush\Commands\DrushCommands;
use Psr\Log\LoggerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * A Drush command file.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 */
class UiowaCoreCommands extends DrushCommands {
  /**
   * The uiowa_core logger channel.
   */
  protected ?LoggerInterface $logger;

  /**
   * The config factory service.
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The module handler service.
   */
  protected ModuleHandler $moduleHandler;

  /**
   * The purge invalidations service.
   */
  protected InvalidationsService $purgeInvalidations;

  /**
   * The purge queuer service.
   */
  protected QueuersService $purgeQueuer;

  /**
   * The purge queue service.
   */
  protected QueueService $purgeQueue;

  /**
   * Command constructor.
   */
  public function __construct(LoggerInterface $logger, ConfigFactoryInterface $configFactory, ModuleHandler $moduleHandler, InvalidationsService $purgeInvalidations, QueuersService $purgeQueuer, QueueService $purgeQueue) {
    $this->logger = $logger;
    $this->configFactory = $configFactory;
    $this->moduleHandler = $moduleHandler;
    $this->purgeInvalidations = $purgeInvalidations;
    $this->purgeQueuer = $purgeQueuer;
    $this->purgeQueue = $purgeQueue;
  }

  /**
   * Toggles Site-Specific Google Tag inserts.
   *
   * @command uiowa_core:toggle-gtag
   * @aliases uicore-gtag
   */
  public function toggleGtag(): void {
    $config = $this->configFactory->getEditable('uiowa_core.settings');
    $uiowa_core_gtag = $config->get('uiowa_core.gtag');

    if ((int) $uiowa_core_gtag === 1) {
      $this->logger->notice('Site-specific Google Tag Manager Disabled');
      $config
        ->set('uiowa_core.gtag', '0')
        ->save();
    }
    else {
      $this->logger->notice('Site-specific Google Tag Manager Enabled');
      $config
        ->set('uiowa_core.gtag', '1')
        ->save();
    }
    // Flush site cache.
    drupal_flush_all_caches();

    // If available (not Local), try to clear the varnish cache for the files.
    if ($this->moduleHandler->moduleExists('purge')) {
      $queuer = $this->purgeQueuer->get('coretags');

      $invalidations = [
        $this->purgeInvalidations->get('everything'),
      ];

      $this->purgeQueue->add($queuer, $invalidations);
    }
  }

  /**
   * Displays custom role mappings if any.
   *
   * @command uiowa_core:custom_role_mappings
   * @aliases uicore-crm
   *
   * @usage uiowa_core:custom_role_mappings
   */
  public function customRoleMappings(): void {
    if ($this->moduleHandler->moduleExists('uiowa_auth')) {
      // Get site's role_mapping values from uiowa_auth module.
      $config = $this->configFactory->getEditable('uiowa_auth.settings');
      $role_mappings = $config->get('role_mappings');

      // Get site default role_mappings from config directory.
      $config_path = DRUPAL_ROOT . '/../config/default';
      $source = new FileStorage($config_path);
      $uiowa_auth = $source->read('uiowa_auth.settings');
      $default_mappings = $uiowa_auth['role_mappings'];

      if (!empty($role_mappings) && !empty($default_mappings)) {
        // Compare site to default mappings.
        $diff = array_diff($role_mappings, $default_mappings);

        if ($diff) {
          // Prettify and dump result.
          $result = Yaml::dump($diff);
          $this->logger->notice($result);
        }
      }
      else {
        $this->logger->notice('Configuration to compare does not exist.');
      }

    }
    else {
      $this->logger->notice('uiowa_auth is not enabled.');
    }
  }

}
