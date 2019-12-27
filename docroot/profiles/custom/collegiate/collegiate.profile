<?php

/**
 * Implements hook_modules_installed().=
 */
function collegiate_modules_installed(array $modules) {
  // Don't do anything during config sync.
  if (\Drupal::isConfigSyncing()) {
    return;
  }

  // Once we have installed collegiate, every other
  // module should be installed, so we are safe to run
  // this code. Adds permission related to the rich text
  // editor and media browser to the webmaster and editor
  // permissions.
  if (in_array('collegiate', $modules)) {
    $permissions = [
      'use text format rich_text',
      'access ckeditor_media_browser entity browser pages',
      'access media_browser entity browser pages',
    ];
    user_role_grant_permissions('webmaster', $permissions);
    user_role_grant_permissions('editor', $permissions);
  }
}
