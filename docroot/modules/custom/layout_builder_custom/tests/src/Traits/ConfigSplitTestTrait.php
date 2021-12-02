<?php

namespace Drupal\Tests\layout_builder_custom\Traits;

use Drupal\Core\Config\FileStorage;

trait ConfigSplitTestTrait {

  /**
   * @var null|\Drupal\config_split\ConfigSplitCliService
   */
  protected $cliService = NULL;

  /**
   * Enable a config split.
   *
   * @param $split_name
   */
  public function enableConfigSplit($split_name) {
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

    $this->cliService()->ioImport($split_name, $this->cliIo(), 't');
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
    $io = new class() {
      public function __call($string, $arguments) {
        return '';
      }
    };

    return $io;
  }
}
