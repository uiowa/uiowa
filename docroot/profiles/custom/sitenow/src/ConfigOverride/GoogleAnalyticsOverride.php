<?php

namespace Drupal\sitenow\ConfigOverride;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ConfigFactoryOverrideInterface;
use Drupal\Core\Config\StorageInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Google Analytics configuration override.
 */
class GoogleAnalyticsOverride implements ConfigFactoryOverrideInterface {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private ConfigFactoryInterface $configFactory;

  /**
   * The request stack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  private RequestStack $requestStack;

  /**
   * Constructs a new GoogleAnalyticsOverride object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack service.
   *
   * @todo Remove $request_stack and the sandbox bypass in loadOverrides()
   *   before merging to main. Added only to keep data streaming on
   *   sandbox.dev.drupal.uiowa.edu while this branch is deployed to develop
   *   ahead of the tmp_google_override sandbox testing branch.
   */
  public function __construct(ConfigFactoryInterface $config_factory, RequestStack $request_stack) {
    $this->configFactory = $config_factory;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public function loadOverrides($names) {
    $overrides = [];
    if (in_array('google_analytics.settings', $names)) {
      // @todo Remove this sandbox bypass before merging to main.
      $request = $this->requestStack->getCurrentRequest();
      $host = $request ? $request->getHost() : '';
      $sandbox_bypass = in_array($host, ['sandbox.dev.drupal.uiowa.edu'], TRUE);

      // Remove GA for local development.
      $env = getenv('AH_SITE_ENVIRONMENT');
      if ($env !== 'prod' && !$sandbox_bypass) {
        $overrides['google_analytics.settings']['account'] = '';
      }

      // Remove GA based on uiowa_core settings.
      $uiowa_core_config = $this->configFactory->get('uiowa_core.settings');
      if ($uiowa_core_config && !$uiowa_core_config->get('uiowa_core.ga')) {
        $overrides['google_analytics.settings']['account'] = '';
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
