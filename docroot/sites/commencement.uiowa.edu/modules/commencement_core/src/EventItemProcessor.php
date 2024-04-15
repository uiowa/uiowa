<?php

namespace Drupal\commencement_core;

use Drupal\uiowa_core\EntityItemProcessorBase;

/**
 * A processor for syncing event nodes.
 */
class EventItemProcessor extends EntityItemProcessorBase {

  /**
   * {@inheritdoc}
   */
  protected static $fieldMap = [
    // Map of 1:1 fields.
    'title' => 'title',
    'body' => 'description_text',
    'field_event_contact' => 'contact_name',
    'field_event_contact_email' => 'contact_email',
    'field_event_contact_phone' => 'contact_phone',
    'field_event_room' => 'room_number',
    'field_event_venue' => 'location_name',
    'field_event_id' => 'id',
  ];

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
      if (property_exists($record, $from) && $entity->get($to)->{static::resolveFieldValuePropName($entity->getFieldDefinition($to))} != $record->{$from}) {
        $entity->set($to, $record->{$from});
        $updated = TRUE;
      }
    }

    return $updated;
  }

}
