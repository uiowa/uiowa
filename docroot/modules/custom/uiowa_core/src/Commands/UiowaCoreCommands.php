<?php

namespace Drupal\uiowa_core\Commands;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\purge\Plugin\Purge\Invalidation\InvalidationsService;
use Drupal\purge\Plugin\Purge\Queue\QueueService;
use Drupal\purge\Plugin\Purge\Queuer\QueuersService;
use Drush\Commands\DrushCommands;
use Psr\Log\LoggerInterface;

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
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * The purge invalidations service.
   *
   * @var \Drupal\purge\Plugin\Purge\Invalidation\InvalidationsService
   */
  protected $purgeInvalidations;

  /**
   * The purge queuer service.
   *
   * @var \Drupal\purge\Plugin\Purge\Queuer\QueuersService
   */
  protected $purgeQueuer;

  /**
   * The purge queue service.
   *
   * @var \Drupal\purge\Plugin\Purge\Queue\QueueService
   */
  protected $purgeQueue;

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
  public function toggleGtag() {
    $config = $this->configFactory->getEditable('uiowa_core.settings');
    $uiowa_core_gtag = $config->get('uiowa_core.gtag');

    if ($uiowa_core_gtag == '1') {
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

}
