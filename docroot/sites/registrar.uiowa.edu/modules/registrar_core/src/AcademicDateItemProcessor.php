<?php

namespace Drupal\registrar_core;

use Drupal\uiowa_core\EntityItemProcessorBase;

/**
 * A processor for syncing event nodes.
 */
class AcademicDateItemProcessor extends EntityItemProcessorBase {

  /**
   * {@inheritdoc}
   */
  protected static $fieldMap = [
    'title' => 'title',
    'body' => 'description_text',
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

}
