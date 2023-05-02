<?php

namespace Drupal\uiowa_core;

/**
 * A base for node processing classes.
 */
abstract class EntityItemProcessorBase {

  /**
   * The map of entity field names to source field names.
   *
   * @var array
   */
  protected static $fieldMap = [];

  /**
   * Process an individual entity.
   */
  public static function process($entity, $record): bool {
    $updated = FALSE;
    foreach (static::$fieldMap as $to => $from) {
      // @todo Add a message if a node doesn't have a field.
      if ($entity->hasField($to) && $entity->get($to)->value != $record->{$from}) {
        $entity->set($to, $record->{$from});
        $updated = TRUE;
      }
    }

    return $updated;
  }

}
