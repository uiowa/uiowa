<?php

namespace Drupal\sitenow\ConfigOverride;

use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Deprecated shim so stale cached containers can still instantiate this.
 *
 * Delegates everything to GoogleTagOverride. Remove once all sites have
 * rebuilt their service container.
 */
class GoogleAnalyticsOverride extends GoogleTagOverride {

  /**
   * Constructs a deprecated GoogleAnalyticsOverride shim.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Symfony\Component\HttpFoundation\RequestStack|null $request_stack
   *   Unused; kept for the old service definition's signature.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ?RequestStack $request_stack = NULL) {
    parent::__construct($config_factory);
  }

}
