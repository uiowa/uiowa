<?php

namespace Drupal\classrooms_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\CacheableMetadata;

/**
 * A 'Room Schedule' block.
 *
 * @Block(
 *   id = "roomschedule_block",
 *   admin_label = @Translation("Room Schedule"),
 *   category = @Translation("Room"),
 * )
 */
class RoomSchedule extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['label_display' => TRUE];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $node = \Drupal::routeMatch()->getParameter('node');

    if (!is_null($node)) {
      $building_id = $node
        ->field_room_building_id
        ?->target_id;
      $room_id = $node
        ->field_room_room_id
        ?->value;

      if (!is_null($building_id) && !is_null($room_id)) {
        // Grab MAUI room data.
        $maui_api = \Drupal::service('uiowa_maui.api');
        $data = $maui_api->getRoomSchedule(date('Y-m-d'), date('Y-m-d'), $building_id, $room_id);

        if (!empty($data)) {
          $items = [];
          // Iterate through the data and push the values to the $items array.
          foreach ($data as $item) {
            $items[] = $item;
          }
          $build = [
            '#theme' => 'room_schedule_list',
            '#items' => $items,
            '#attached' => [
              'library' => [
                'classrooms_core/room_schedule_list',
              ],
            ],
            '#cache' => [
              'tags' => ['time:hourly'],
              'max-age' => 60,
            ],
          ];
        }
        else {
          $build = [
            '#prefix' => '<div class="room-schedule__no-results">',
            '#suffix' => '</div>',
            '#markup' => '<h2 class="h4 block__headline headline headline--serif headline--underline">Today\'s Schedule</h2><p class="room-schedule__no-results__message">There are no scheduled events for this room today.</p>',
          ];
        }

        $cache = new CacheableMetadata();
        $cache->setCacheTags(['time:hourly']);
        $cache->applyTo($build);
      }
    }

    return $build;
  }

}
