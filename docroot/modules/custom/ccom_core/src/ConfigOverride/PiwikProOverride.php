<?php

namespace Drupal\ccom_core\ConfigOverride;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryOverrideInterface;
use Drupal\Core\Config\StorageInterface;

/**
 * Piwik Pro configuration override.
 */
class PiwikProOverride implements ConfigFactoryOverrideInterface {

  /**
   * {@inheritdoc}
   */
  public function loadOverrides($names) {
    $overrides = [];
    if (in_array('piwik_pro.settings', $names)) {
      $env = getenv('AH_SITE_ENVIRONMENT');

      if ($env !== 'prod') {
        $overrides['piwik_pro.settings']['site_id'] = '';
        $overrides['piwik_pro.settings']['piwik_domain'] = '';
      }
    }

    return $overrides;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheSuffix() {
    return 'GoogleAnalyticsOverride';
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
