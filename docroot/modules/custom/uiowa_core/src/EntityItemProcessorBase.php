<?php

namespace Drupal\uiowa_core;

use Drupal\Core\Field\FieldDefinitionInterface;

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
      // If the value is different, update it.
      if ($entity->get($to)->{static::resolveFieldValuePropName($entity->getFieldDefinition($to))} != $record->{$from}) {
        $entity->set($to, $record->{$from});
        $updated = TRUE;
      }
    }

    return $updated;
  }

  /**
   * Determine the correct name of the value property to check against.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $definition
   *   The field definition.
   *
   * @return string
   *   The property name.
   */
  protected static function resolveFieldValuePropName(FieldDefinitionInterface $definition) {
    return match ($definition->getType()) {
      'entity_reference' => 'target_id',
      'link' => 'uri',
      default => 'value',
    };
  }

}
