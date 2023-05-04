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
      if (!$entity->hasField($to)) {
        // @todo Add a message if a node doesn't have a field.
        continue;
      }
      $value_prop = 'value';
      // Check if it's an entity reference field, and set $value_prop
      // accordingly.
      if ($entity->getFieldDefinition($to)->getType() === 'entity_reference') {
        $value_prop = 'target_id';
      }
      // If the value is different, update it.
      if ($entity->get($to)->{$value_prop} != $record->{$from}) {
        $entity->set($to, $record->{$from});
        $updated = TRUE;
      }
    }

    return $updated;
  }

}
