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
    'title' => 'title',
    'field_event_contact' => 'contact_name',
    'field_event_contact_email' => 'contact_email',
    'field_event_contact_phone' => 'contact_phone',
    'field_event_room' => 'room_number',
    'field_event_venue' => 'location_name',
    'field_event_id' => 'id',
    'field_event_link' => 'events_site_url',
    'field_event_website' => 'url',
    'field_event_when:value' => 'start',
    'field_event_when:end_value' => 'end',
    'field_event_when:duration' => 'duration',
  ];

  /**
   * {@inheritdoc}
   */
  public static function process($entity, $record): bool {
    $updated = parent::process($entity, $record);

    // Handle the body field.
    if (isset($record->description)) {
      if ($entity->get('body')->value !== $record->description) {
        // Set both value and format for the body field.
        $entity->set('body', [
          'value' => $record->description,
          'format' => 'filtered_html',
        ]);
        $updated = TRUE;
      }
    }

    return $updated;
  }

  /**
   * {@inheritdoc}
   */
  protected static function prepareUpdatedValues(array &$values, $entity, $record): void {
    // If any values for the date field are being updated, ensure that both
    // start and end are included in the update values.
    if (isset($values['field_event_when'])) {
      // If the event start time is being updated, ensure the end time is
      // updated too.
      if (isset($values['field_event_when']['value'])
        && !isset($values['field_event_when']['end_value'])
        && property_exists($record, 'end')) {
        $values['field_event_when']['end_value'] = $record->end;
      }
      // If the event end time is being updated, ensure the start time is
      // updated too.
      if (isset($values['field_event_when']['end_value'])
        && !isset($values['field_event_when']['value'])
        && property_exists($record, 'start')) {
        $values['field_event_when']['value'] = $record->start;
      }
    }
  }

}
