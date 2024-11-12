<?php

namespace Drupal\sitenow\ConfigOverride;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ConfigFactoryOverrideInterface;
use Drupal\Core\Config\StorageInterface;

/**
 * Google Analytics configuration override.
 */
class GoogleAnalyticsOverride implements ConfigFactoryOverrideInterface {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|null
   */
  private $configFactory;

  /**
   * Constructs a new GoogleAnalyticsOverride object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface|null $config_factory
   *   The config factory service.
   */
  public function __construct(?ConfigFactoryInterface $config_factory = NULL) {
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function loadOverrides($names) {
    $overrides = [];
    if (in_array('google_analytics.settings', $names)) {
      // Remove GA for local development.
      $env = getenv('AH_SITE_ENVIRONMENT');
      if ($env !== 'prod') {
        $overrides['google_analytics.settings']['account'] = '';
      }

      if ($this->configFactory) {
        // Remove GA based on uiowa_core settings.
        $uiowa_core_config = $this->configFactory->get('uiowa_core.settings');
        $include_ga = $uiowa_core_config->get('uiowa_core.ga');

        if (!$include_ga) {
          $overrides['google_analytics.settings']['account'] = '';
        }
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
