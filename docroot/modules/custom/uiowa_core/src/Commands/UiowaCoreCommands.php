<?php

namespace Drupal\uiowa_core\Commands;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Logger\LoggerChannelTrait;
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
   * Command constructor.
   */
  public function __construct(LoggerInterface $logger, ConfigFactoryInterface $configFactory, ModuleHandler $moduleHandler) {
    $this->logger = $logger;
    $this->configFactory = $configFactory;
    $this->moduleHandler = $moduleHandler;
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
