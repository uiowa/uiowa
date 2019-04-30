<?php

/**
 * @file
 * Single event theme functions.
 */

/**
 * Preprocess a single event.
 *
 * @param array &$variables
 *   Array reference of arguments given to the theme() function.
 */
function template_preprocess_uiowa_events_single_event(array &$variables) {
  // Add single-event css/js.
  $variables['#attached']['library'][] = 'uiowa_events/single-event';
  // Quick hack to get the data structured properly.
  // This is due to the standardize parsing of data for all events.
  // @see uiowa_events_prepare_data.
  $variables['data'] = $variables['data'][0];

  // All event instances are passed in the event_instances array.
  $instances = [];
  $instance_arg = 0;
  // If there are more than 1 instances, use the last URL argument to get the requested instance.
  // If there are no instances, set the current instance to $variables['data']['event_instances'][0].
  if (count($variables['data']['event_instances']) > 1) {
    $path = explode('/', \Drupal::service('path.current')->getPath());
    if (count($path) > 2) {
      $instance_arg = array_pop($path);
    }
  }
  $current_instance = $variables['data']['event_instances'][$instance_arg];
  // Set the current instance on $data.
  $variables['data']['instance_start'] = $current_instance['event_instance']['start'];
  $variables['data']['instance_end'] = $current_instance['event_instance']['end'];
  $variables['data']['instance_all_day'] = $current_instance['event_instance']['all_day'];
  // Insert the current instance as the first element in the $instances array (to be displayed first).
  $instances[] = [
    '#type' => 'container',
    '#attributes' => [
      'class' => [
        'date-instance',
        'date-instance__next-upcoming',
      ],
    ],
    '#next_upcoming' => TRUE,
    'date' => [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => uiowa_events_format_instance($current_instance['event_instance']),
      '#attributes' => [
        'class' => [
          'date-instance__date',
        ],
      ],
    ],
  ];
  // Remove the current instance from the event_instances array to avoid a duplicate date.
  unset($variables['data']['event_instances'][$instance_arg]);
  // Loop through and find the next upcoming and set instance_next_upcoming.
  foreach ($variables['data']['event_instances'] as $instance) {
    // All day events start at midnight.
    // If instance is all day, set the timestamp to today at midnight.
    $now = ($instance['event_instance']['all_day'] === TRUE) ? strtotime('today midnight') : time();
    // Assumes instances are in ascending order.
    if (strtotime($instance['event_instance']['start']) >= $now) {
      $instances[] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'date-instance',
            'date-instance__future',
          ],
        ],
        '#upcoming' => TRUE,
        'date' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => uiowa_events_format_instance($instance['event_instance']),
          '#attributes' => [
            'class' => [
              'date-instance__date',
            ],
          ],
        ],
      ];
    }
    else {
      $instances[] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'date-instance',
            'date-instance__past',
          ],
        ],
        '#past' => TRUE,
        'date' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => uiowa_events_format_instance($instance['event_instance']),
          '#attributes' => [
            'class' => [
              'date-instance__date',
            ],
          ],
        ],
      ];
    }
  }
  $variables['data']['date_instances'] = $instances;
  if (isset($variables['data']['photo_url'])) {
    $variables['data']['event_image'] = [
      '#theme' => 'imagecache_external',
      '#uri' => $variables['data']['photo_url'],
      '#style_name' => 'medium',
      '#alt' => t('@title promotional image', ['@title' => $variables['data']['title']]),
    ];
  }
}
