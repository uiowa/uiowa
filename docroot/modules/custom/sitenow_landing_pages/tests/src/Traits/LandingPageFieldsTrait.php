<?php

namespace Drupal\Tests\sitenow_landing_pages\Traits;

use Drupal\Core\Config\FileStorage;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Creates the landing page fields from the feature split's config.
 */
trait LandingPageFieldsTrait {

  /**
   * Creates the landing page fields and adds them to the page form.
   */
  protected function createLandingPageFields(): void {
    $storage = new FileStorage(DRUPAL_ROOT . '/../config/features/landing_pages');

    foreach (['field_landing_page', 'field_landing_page_site_name'] as $field) {
      FieldStorageConfig::create($storage->read("field.storage.node.$field"))->save();
      FieldConfig::create($storage->read("field.field.node.page.$field"))->save();

      \Drupal::service('entity_display.repository')
        ->getFormDisplay('node', 'page')
        ->setComponent($field, ['type' => 'boolean_checkbox'])
        ->save();
    }
  }

}
