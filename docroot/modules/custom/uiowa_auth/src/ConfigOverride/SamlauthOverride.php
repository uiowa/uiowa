<?php

namespace Drupal\uiowa_auth\ConfigOverride;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryOverrideInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Samlauth configuration overrides.
 */
class SamlauthOverride implements ConfigFactoryOverrideInterface {
  /**
   * The entity_type.manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public function loadOverrides($names) {
    $overrides = [];

    // Allow all roles to be linkable.
    if (in_array('samlauth.authentication', $names)) {

      $roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple();
      $allowed = [];

      /** @var \Drupal\user\Entity\Role $role */
      foreach ($roles as $role) {
        $id = $role->id();

        if ($id == 'anonymous' || $id == 'authenticated') {
          continue;
        }

        $allowed[$id] = $id;
      }

      $overrides['samlauth.authentication']['map_users_roles'] = $allowed;
    }

    return $overrides;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheSuffix() {
    return 'SamlauthOverride';
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
