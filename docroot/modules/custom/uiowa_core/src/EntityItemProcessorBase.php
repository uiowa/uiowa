<?php

namespace Drupal\uiowa_core;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Logger\LoggerChannelTrait;

/**
 * A base for node processing classes.
 */
abstract class EntityItemProcessorBase {
  use LoggerChannelTrait;

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
        // Add a log message that the field being mapped to doesn't exist.
        static::getLogger('uiowa_core')->notice('While processing the @type, a field was mapped that does not exist: @field_name', [
          '@type' => !is_null($entity->bundle()) ? "{$entity->bundle()} {$entity->getEntityType()}" : $entity->getEntityType(),
          '@field_name' => $to,
        ]);
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
      'entity_reference', 'image' => 'target_id',
      'link' => 'uri',
      default => 'value',
    };
  }

}
