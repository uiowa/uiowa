<?php

namespace Drupal\Tests\layout_builder_custom\Traits;

use Drupal\Core\Config\FileStorage;

/**
 * A trait for enabling a config split in a test.
 */
trait ConfigSplitTestTrait {

  /**
   * The CLI service.
   *
   * @var null|\Drupal\config_split\ConfigSplitCliService
   */
  protected $cliService = NULL;

  /**
   * Enable a config split.
   *
   * @param string $split_name
   *   The split being enabled.
   * @param null|\Drupal\user\UserInterface $user
   *   The user to give permissions to.
   */
  public function enableConfigSplit($split_name, $user = NULL) {
    // @todo Add check that split exists.
    // Import the split configuration.
    $config_path = DRUPAL_ROOT . '/../config/default';
    $source = new FileStorage($config_path);
    $config_storage = \Drupal::service('config.storage');
    $split = $source->read("config_split.config_split.$split_name");
    $config_storage->write("config_split.config_split.$split_name", $split);

    // Enable the split.
    $config_factory = \Drupal::configFactory();
    $config = $config_factory->getEditable("config_split.config_split.$split_name");
    $config->set('status', TRUE);
    $config->save(TRUE);

    if (is_null($user)) {
      $user = $this->drupalCreateUser(['synchronize configuration']);
    }
    $this->drupalLogin($user);

    // Import the configuration thereby re-installing all the modules.
    $this->drupalGet('admin/config/development/configuration');
    // @todo Figure out how to fix message about staged configuration.
    $this->submitForm([], 'Import all');
    // Modules have been installed that have services.
    $this->rebuildContainer();
  }

  /**
   * Load the config_split.cli service.
   */
  protected function cliService() {
    if (is_null($this->cliService)) {
      $this->cliService = $this->container->get('config_split.cli');
    }
    return $this->cliService;
  }

  /**
   * A mock io object for testing.
   */
  protected function cliIo() {
    // Anonymous function to mock $io calls.
    $io = new class() {

      /**
       * {@inheritdoc}
       */
      public function __call($string, $arguments) {
        return '';
      }

    };

    return $io;
  }

}
