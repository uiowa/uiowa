<?php

namespace Drupal\uiowa_core;

use Drupal\Core\Entity\FieldableEntityInterface;
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
  public static function process(FieldableEntityInterface $entity, $record): bool {
    $updated = FALSE;
    $values = [];
    foreach (static::$fieldMap as $to => $from) {
      $value_property = NULL;
      if (str_contains($to, ':')) {
        [$to, $value_property] = explode(':', $to);
      }
      if (!$entity->hasField($to)) {
        // Add a log message that the field being mapped to doesn't exist.
        (new static)->getLogger('uiowa_core')->notice('While processing the @type, a field was mapped that does not exist: @field_name', [
          '@type' => !is_null($entity->bundle()) ? "{$entity->bundle()} {$entity->getType()}" : $entity->getType(),
          '@field_name' => $to,
        ]);
        continue;
      }

      // If the value is different, update it.
      if (property_exists($record, $from)) {
        // If a value property wasn't derived from the field map, set a default
        // one.
        if (is_null($value_property)) {
          $value_property = static::resolveFieldValuePropName($entity->getFieldDefinition($to));
        }
        $entity_value = $entity->get($to)->{$value_property};
        // If the property has changed, update it. We are deliberately not doing
        // a type check because we don't care if an integer is not a string.
        if ((is_null($entity_value) && !is_null($record->{$from})) || $entity_value != $record->{$from}) {
          $values[$to][$value_property] = $record->{$from};
          $updated = TRUE;
        }
      }
    }

    foreach ($values as $field => $value) {
      $entity->set($field, $value);
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
  protected static function resolveFieldValuePropName(FieldDefinitionInterface $definition): string {
    return match ($definition->getType()) {
      'entity_reference',
      'image' => 'target_id',
      'link' => 'uri',
      default => 'value',
    };
  }

}
