<?php

namespace Drupal\sitenow\ConfigOverride;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryOverrideInterface;
use Drupal\Core\Config\StorageInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Simple sitemap configuration overrides.
 */
class SimpleSitemapOverride implements ConfigFactoryOverrideInterface {
  /**
   * The RequestStack service.
   */
  protected RequestStack $requestStack;

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

    if (in_array('simple_sitemap.settings', $names)) {
      // Set the base URL so the sitemap uses the https scheme.
      $request = $this->requestStack->getCurrentRequest();
      $host = $request->getHost();

      $overrides['simple_sitemap.settings']['base_url'] = "https://{$host}";
    }

    return $overrides;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheSuffix() {
    return 'SimpleSitemapOverride';
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
