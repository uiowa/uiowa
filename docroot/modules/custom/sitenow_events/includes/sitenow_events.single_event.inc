<?php

/**
 * @file
 * Single event theme functions.
 */

use Drupal\Component\Utility\Xss;

/**
 * Preprocess a single event.
 *
 * @param array &$variables
 *   Array reference of arguments given to the theme() function.
 */
function template_preprocess_sitenow_events_single_event(array &$variables) {
  $variables['#attached']['library'][] = 'sitenow_events/single-event';

  // Filter the description for any XSS.
  $variables['data']['description'] = Xss::filterAdmin($variables['data']['description']);

  // Prepare the a11y statement.
  $variables['data']['accessibility_statement'] = t('Individuals with disabilities are encouraged to attend all University of Iowa–sponsored events. If you are a person with a disability who requires a reasonable accommodation in order to participate in this program, please contact @c_name in advance at @c_ph or @c_email.', [
    '@c_name' => $variables['data']['contact_name'],
    '@c_ph' => $variables['data']['contact_phone'],
    '@c_email' => $variables['data']['contact_email'],
  ]);

  if (isset($variables['data']['event_instances'])) {
    $instances = [];
    $instance_arg = 0;

    // If there are more than 1 instances, use the last URL argument to get the
    // requested instance. If there are no instances, set the current instance
    // to $variables['data']['event_instances'][0].
    if (count($variables['data']['event_instances']) > 1) {
      $path = explode('/', \Drupal::service('path.current')->getPath());

      if (count($path) > 2) {
        $instance_arg = array_pop($path);
      }
    }

    // Set the current instance on $data.
    $variables['data']['instance_start'] = $variables['data']['event_instances'][$instance_arg]['event_instance']['start'];
    $variables['data']['instance_end'] = $variables['data']['event_instances'][$instance_arg]['event_instance']['end'];
    $variables['data']['instance_all_day'] = $variables['data']['event_instances'][$instance_arg]['event_instance']['all_day'];

    // Insert the current instance as the first element in the $instances
    // array (to be displayed first).
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
        '#value' => sitenow_events_format_instance($variables['data']['event_instances'][$instance_arg]['event_instance']),
        '#attributes' => [
          'class' => [
            'date-instance__date',
          ],
        ],
      ],
    ];

    // Remove the current instance from the event_instances array to avoid a
    // duplicate date.
    unset($variables['data']['event_instances'][$instance_arg]);

    // Loop through and find the next upcoming and set instance_next_upcoming.
    foreach ($variables['data']['event_instances'] as $instance) {
      // All day events start at midnight. If instance is all day, set the
      // timestamp to today at midnight.
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
            '#value' => sitenow_events_format_instance($instance['event_instance']),
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
            '#value' => sitenow_events_format_instance($instance['event_instance']),
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
  }
  if (isset($variables['data']['photo_url'])) {
    $variables['data']['event_image'] = [
      '#theme' => 'imagecache_external',
      '#uri' => $variables['data']['photo_url'],
      '#style_name' => 'medium',
      '#alt' => t('@title promotional image', ['@title' => $variables['data']['title']]),
    ];
  }
}
