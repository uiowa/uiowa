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
      // Check if it's an entity reference field, and manipulate
      // if necessary.
      if ($entity->getFieldDefinition($to)->getType() === 'entity_reference') {
        if (array_column($entity->get($to)->getValue(), 'target_id') != $record->{$from}) {
          $entity->set($to, $record->{$from});
          $updated = TRUE;
        }
      }
      // It's not an entity reference field, so we can grab
      // the value from it directly.
      elseif ($entity->get($to)->value != $record->{$from}) {
        $entity->set($to, $record->{$from});
        $updated = TRUE;
      }
    }

    return $updated;
  }

}
