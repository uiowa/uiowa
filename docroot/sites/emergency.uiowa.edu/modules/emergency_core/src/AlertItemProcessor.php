<?php

namespace Drupal\emergency_core;

use Drupal\uiowa_core\EntityItemProcessorBase;

/**
 * A processor for syncing hawk alert nodes.
 */
class AlertItemProcessor extends EntityItemProcessorBase {

  /**
   * {@inheritdoc}
   */
  protected static $fieldMap = [
    'title' => 'headline',
    'body' => 'description',
    'field_alert_identifier' => 'identifier',
  ];

  /**
   * Process an individual hawk alert.
   */
  public static function process($entity, $record): bool {
    $updated = FALSE;
    foreach (static::$fieldMap as $to => $from) {
      if (!$entity->hasField($to)) {
        // Add a log message that the field being mapped to doesn't exist.
        static::getLogger('emergency_core')
          ->notice('While processing the @type, a field was mapped that does not exist: @field_name', [
            '@type' => !is_null($entity->bundle()) ? "{$entity->bundle()} {$entity->getEntityType()}" : $entity->getEntityType(),
            '@field_name' => $to,
          ]);
        continue;
      }

      // If the value is different, update it.
      if ($entity->get($to)->{static::resolveFieldValuePropName($entity->getFieldDefinition($to))} != $record[$from]) {
        $entity->set($to, $record[$from]);
        $updated = TRUE;
      }
    }

    return $updated;
  }

}
