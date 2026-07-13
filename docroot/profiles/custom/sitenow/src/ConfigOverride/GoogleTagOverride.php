<?php

namespace Drupal\sitenow\ConfigOverride;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ConfigFactoryOverrideInterface;
use Drupal\Core\Config\StorageInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Google Tag configuration override.
 *
 * Disables containers when Google Tag is off (uiowa_core.gtag === 0).
 * Overriding status (not tag_container_ids) matters for two reasons: the
 * override merge is a deep merge so an empty array can't clear a sequence,
 * and TagContainerResolver's query filters on status before loading
 * entities, so a disabled container is never evaluated - including ones
 * with broken condition data that would otherwise crash render. Admin
 * forms use loadMultipleOverrideFree() so owners still see/edit the real
 * container.
 */
class GoogleTagOverride implements ConfigFactoryOverrideInterface {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private ConfigFactoryInterface $configFactory;

  /**
   * The request stack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack|null
   */
  private ?RequestStack $requestStack;

  /**
   * Constructs a new GoogleTagOverride object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Symfony\Component\HttpFoundation\RequestStack|null $request_stack
   *   The request stack service, used to identify the sandbox site.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ?RequestStack $request_stack = NULL) {
    $this->configFactory = $config_factory;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public function loadOverrides($names) {
    $overrides = [];

    // Only read uiowa_core.settings when it's actually needed - doing so
    // unconditionally re-invokes this override for that name and recurses
    // infinitely.
    $container_names = array_filter($names, static fn ($name) => str_starts_with($name, 'google_tag.container.'));

    if (!$container_names) {
      return $overrides;
    }

    $gtag_disabled = !(bool) $this->configFactory
      ->get('uiowa_core.settings')
      ->get('uiowa_core.gtag');

    // @todo Remove this sandbox carve-out once dev testing of the
    // migration is complete - it's here only so sandbox.dev can see real
    // container data while every other non-prod site keeps the normal
    // suppression below.
    $host = $this->requestStack?->getCurrentRequest()?->getHost();
    $is_sandbox = $host === 'sandbox.dev.drupal.uiowa.edu';
    $is_prod = getenv('AH_SITE_ENVIRONMENT') === 'prod';

    // Disable Google Tag containers on non-prod (except sandbox, for now)
    // or when gtag is disabled.
    if ((!$is_prod && !$is_sandbox) || $gtag_disabled) {
      foreach ($container_names as $name) {
        $overrides[$name]['status'] = FALSE;
      }
    }

    return $overrides;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheSuffix() {
    return 'GoogleTagOverride';
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata($name) {
    $metadata = new CacheableMetadata();
    if (str_starts_with($name, 'google_tag.container.')) {
      $metadata->addCacheTags(['config:uiowa_core.settings']);
    }
    return $metadata;
  }

  /**
   * {@inheritdoc}
   */
  public function createConfigObject($name, $collection = StorageInterface::DEFAULT_COLLECTION) {
    return NULL;
  }

}
