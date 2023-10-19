<?php

namespace Drupal\uiowa_core;

/**
 * A class to help with region rendering and overrides.
 */
class RegionHelper {

  /**
   * The allowed regions that can be overridden.
   */
  const ALLOWED_REGIONS = [
    'pre_footer',
    'after_content',
  ];

  /**
   * Custom node content type form defaults.
   */
  public static function overrideNodeForm(&$form): void {
    if (empty($form)) {
      return;
    }
    // This checks if any region in the region list exists.
    $override_fields_exist = FALSE;
    foreach (self::ALLOWED_REGIONS as $region) {
      $field_name = "field_{$region}_override";
      if (isset($form[$field_name])) {
        $override_fields_exist = TRUE;
        // Set region to region_overrides group.
        $form[$field_name]['#group'] = 'region_overrides';
      }
    }

    if ($override_fields_exist) {
      // Create region_overrides group in advanced container.
      $form['region_overrides'] = [
        '#type' => 'details',
        '#title' => t('Region overrides'),
        '#group' => 'advanced',
        '#attributes' => [
          'class' => ['node-form-region-overrides'],
        ],
        '#attached' => [
          'library' => ['node/drupal.node'],
        ],
        '#weight' => -3,
        '#optional' => TRUE,
        '#open' => FALSE,
      ];
    }
  }

}
