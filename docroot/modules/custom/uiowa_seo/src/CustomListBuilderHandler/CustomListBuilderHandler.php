<?php

namespace Drupal\uiowa_seo\CustomListBuilderHandler;

use Drupal\metatag\MetatagDefaultsListBuilder;

/**
 * Defines the custom access control handler for the user entity type.
 */
class CustomListBuilderHandler extends MetatagDefaultsListBuilder {

  /**
   * {@inheritdoc}
   */
  public function load() {
    $entities = parent::load();

    $user = \Drupal::currentUser()->getRoles();
    if (!in_array("administrator", $user)) {

      // Return only the global entity.
      if (isset($entities['global'])) {
        return ['global' => $entities['global']];
      }
    }
    else {
      if (isset($entities['global'])) {
        return ['global' => $entities['global']] + $entities;
      }
      else {
        return $entities;
      }
    }
  }

}
