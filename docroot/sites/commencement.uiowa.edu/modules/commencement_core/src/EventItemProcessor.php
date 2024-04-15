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

}
