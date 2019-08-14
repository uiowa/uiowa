<?php

namespace Drupal\sitenow\ConfigOverride;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryOverrideInterface;
use Drupal\Core\Config\StorageInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Acquia connector configuration overrides.
 */
class AcquiaConnectorOverride implements ConfigFactoryOverrideInterface {
  /**
   * The RequestStack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructor to inject dependencies.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $stack
   *   The request stack.
   */
  public function __construct(RequestStack $stack) {
    $this->requestStack = $stack;
  }

  /**
   * {@inheritdoc}
   */
  public function loadOverrides($names) {
    $overrides = [];

    if (in_array('acquia_connector.settings', $names)) {
      // Set the multisite name so the reports are easy to distinguish.
      $request = $this->requestStack->getCurrentRequest();
      $site_name = $request->getHost();

      // Disable subscription data for non-production environments. SPI data
      // will still be collected and it can be assessed for non-production
      // environments using the Drush commands included with the
      // acquia_connector module.
      $is_prod = getenv('AH_PRODUCTION');

      if ($is_prod) {
        $use_cron = TRUE;
      }
      else {
        $use_cron = FALSE;
      }

      $overrides['acquia_connector.settings']['spi']['use_cron'] = $use_cron;
      $overrides['acquia_connector.settings']['site_name'] = $site_name;
      $overrides['acquia_connector.settings']['hide_signup_messages'] = TRUE;
    }

    return $overrides;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheSuffix() {
    return 'AcquiaConnectorOverride';
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
