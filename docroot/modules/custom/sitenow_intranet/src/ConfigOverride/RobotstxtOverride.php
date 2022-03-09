<?php

namespace Drupal\sitenow_intranet\ConfigOverride;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryOverrideInterface;
use Drupal\Core\Config\StorageInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Robotstxt configuration overrides.
 */
class RobotstxtOverride implements ConfigFactoryOverrideInterface {

  /**
   * {@inheritdoc}
   */
  public function loadOverrides($names) {
    $overrides = [];

    if (in_array('robotstxt.settings', $names)) {
      $overrides['robotstxt.settings']['content'] = "User-agent: *\r\nDisallow: /";
    }

    return $overrides;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheSuffix() {
    return 'RobotstxtOverride';
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata($name) {
    return new CacheableMetadata();
  }

  /**
   * {@inheritdoc}
   */
  public function createConfigObject($name, $collection = StorageInterface::DEFAULT_COLLECTION) {
    return NULL;
  }

}
