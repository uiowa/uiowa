<?php

/**
 * @file
 * Profile functions.
 */

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;

/**
 * Implements hook_modules_installed().
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

/**
 * Implements hook_form_FORM_ID_alter().
 */
function collegiate_form_node_confirm_form_alter(&$form, FormStateInterface $form_state) {
  // Check/prevent front page from being deleted on single delete.
  // Only need to alter the delete operation form.
  if ($form_state->getFormObject()->getOperation() !== 'delete') {
    return;
  }
  $node = $form_state
    ->getFormObject()
    ->getEntity();

  // Get and dissect front page path.
  $front = \Drupal::config('system.site')->get('page.front');
  $url = Url::fromUri("internal:" . $front);

  if ($url->isRouted()) {
    $params = $url->getRouteParameters();

    if (isset($params['node']) && $params['node'] == $node->id()) {
      // Disable the 'Delete' button.
      $form['actions']['submit']['#disabled'] = TRUE;
      _collegiate_prevent_front_delete_message($node->getTitle());
    }
  }
}

/**
 * Implements hook_form_FORM_ID_alter().
 */
function collegiate_form_node_delete_multiple_confirm_form_alter(&$form) {
  // Check/prevent front page from being deleted on bulk delete.
  // Get and dissect front page path.
  $front = \Drupal::config('system.site')->get('page.front');
  $params = Url::fromUri("internal:" . $front)->getRouteParameters();
  if (isset($params['node'])) {
    // Loop through until there is a front page match.
    foreach ($form['entities']['#items'] as $item => $title) {
      // Formatted as {nid}:{lang}.
      $item = explode(':', $item);
      if ($params['node'] == $item[0]) {
        // Disable the 'Delete' button.
        $form['actions']['submit']['#disabled'] = TRUE;
        _collegiate_prevent_front_delete_message($title);
        break;
      }
    }
  }
}

/**
 * Custom warning message for front page deletion detection.
 *
 * @param string $title
 *   The front page match content's title.
 *
 * @see collegiate_form_node_confirm_form_alter()
 * @see collegiate_form_node_delete_multiple_confirm_form_alter()
 */
function _collegiate_prevent_front_delete_message($title) {
  // Print warning message informing user to use basic site settings.
  $url = Url::fromRoute('system.site_information_settings', [], ['fragment' => 'edit-site-frontpage']);
  $settings_link = Link::fromTextAndUrl(t('change the front page'), $url)
    ->toString();
  $warning_text = t('The content <em>"@title"</em> is currently set as the front page for this site. You must @settings_link before deleting this content.', [
    '@settings_link' => $settings_link,
    '@title' => $title,
  ]);
  \Drupal::messenger()->addWarning($warning_text);
}
