<?php

/**
 * @file
 * Install tasks.
 */

/**
 * Add tag display type to pages.
 */
function sitenow_pages_update_9302() {
  $changes = [
    'tag_display',
    'related_display',
  ];

  $config = \Drupal::configFactory()->getEditable('sitenow_pages.settings');
  $config->set('tag_display', 'do_not_display');
  $config->set('related_display', 'do_not_display');

  $config->save();
}
