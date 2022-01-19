<?php

namespace Drupal\uiowa_auth\ConfigOverride;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryOverrideInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\user\Entity\Role;

/**
 * Samlauth configuration overrides.
 */
class SamlauthOverride implements ConfigFactoryOverrideInterface {

  /**
   * {@inheritdoc}
   */
  public function loadOverrides($names) {
    $overrides = [];

    // Allow all roles to be linkable.
    if (in_array('samlauth.authentication', $names)) {

      $roles = Role::loadMultiple();
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
