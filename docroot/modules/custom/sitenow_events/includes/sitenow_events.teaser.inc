<?php

/**
 * @file
 * Teaser theme functions.
 */

use Drupal\Component\Utility\Unicode;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Url;

/**
 * Theme function for an event teaser.
 *
 * @param array $variables
 *   Variables from the theme function.
 */
function template_preprocess_sitenow_events_teaser(array &$variables) {
  $config = \Drupal::config('sitenow_events.settings');

  if ($variables['event']['canceled'] == TRUE) {
    $variables['event']['subtitle'] = [
      '#markup' => 'Canceled',
      '#prefix' => '<span class="badge badge--orange">',
      '#suffix' => '</span>',
    ];
  }

  if (isset($variables['event']['media'][0]['medium_image'])) {
    $variables['event']['image'] = [
      '#theme' => 'imagecache_external',
      '#uri' => $variables['event']['media'][0]['medium_image'],
      '#style_name' => 'medium',
      '#alt' => $variables['event']['title'],
      '#attributes' => [
        'data-lazy' => TRUE,
      ],
    ];
  }

  $external_link = ($config->get('event_link') == 'event-link-external');

  if ($external_link) {
    $variables['event']['url'] = $variables['event']['events_site_url'];
  }
  else {
    $single_event_path = $config->get('single_event_path');

    // Add a parameter on the URL for event instances.
    foreach ($variables['event']['event_instances'] as $key => $instance) {
      if ($variables['event']['instance_start'] == $instance['event_instance']['start']) {
        $instance_id = $key;
      }
    }

    $event_path = isset($instance_id) ? "{$variables['event']['id']}/{$instance_id}" : $variables['event']['id'];
    $variables['event']['url'] = Url::fromUri("internal:/{$single_event_path}/{$event_path}", ['alias' => TRUE]);
  }

  $variables['event']['date_string'] = sitenow_events_format_instance([
    'start' => $variables['event']['instance_start'],
    'end' => $variables['event']['instance_end'],
    'all_day' => $variables['event']['instance_all_day'],
  ]);

  if (isset($variables['event']['description_text'])) {
    $variables['event']['description_text'] = Xss::filter($variables['event']['description_text']);
    $variables['event']['description_text'] = Unicode::truncate($variables['event']['description_text'], 500, TRUE, TRUE);
  }
}
