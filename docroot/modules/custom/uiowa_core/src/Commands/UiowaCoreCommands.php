<?php

namespace Drupal\uiowa_core\Commands;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Logger\LoggerChannelTrait;
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
  use LoggerChannelTrait;

  /**
   * The uiowa_core logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected ?LoggerInterface $logger;

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
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Command constructor.
   */
  public function __construct(LoggerInterface $logger, ConfigFactoryInterface $configFactory, ModuleHandler $moduleHandler, InvalidationsService $purgeInvalidations, QueuersService $purgeQueuer, QueueService $purgeQueue, EntityTypeManagerInterface $entityTypeManager) {
    $this->logger = $logger;
    $this->configFactory = $configFactory;
    $this->moduleHandler = $moduleHandler;
    $this->purgeInvalidations = $purgeInvalidations;
    $this->purgeQueuer = $purgeQueuer;
    $this->purgeQueue = $purgeQueue;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Toggles a Google Tag container's enabled status.
   *
   * This toggles the container entity itself (status), which
   * TagContainerResolver excludes at the query level before
   * ever loading a disabled entity. Use this if a broken/rogue container's
   * conditions or settings are causing render-time errors that block the
   * admin UI.
   *
   * @param string $id
   *   The google_tag_container ID to toggle.
   *
   * @command uiowa_core:toggle-gtag
   * * @aliases uicore-gtag
   *
   * @usage uiowa_core:toggle-gtag my_container
   *
   * @throws \Exception
   */
  public function toggleGtag(string $id): void {
    $container = $this->entityTypeManager->getStorage('google_tag_container')->load($id);
    if (!$container) {
      throw new \Exception("No google_tag_container entity found with ID '{$id}'.");
    }

    $container->set('status', !$container->status())->save();
    $this->getLogger('uiowa_core')->notice(($container->status() ? 'Enabled' : 'Disabled') . " google_tag_container '{$container->id()}'.");

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
  public function customRoleMappings() {
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
          $this->getLogger('uiowa_core')->notice($result);
        }
      }
      else {
        $this->getLogger('uiowa_core')->notice('Configuration to compare does not exist.');
      }

    }
    else {
      $this->getLogger('uiowa_core')->notice('uiowa_auth is not enabled.');
    }
  }

}
