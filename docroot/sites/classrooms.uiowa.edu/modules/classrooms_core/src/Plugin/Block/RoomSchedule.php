<?php

namespace Drupal\classrooms_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * A 'Room Schedule' block.
 *
 * @Block(
 *   id = "roomschedule_block",
 *   admin_label = @Translation("Room Schedule"),
 *   category = @Translation("Room"),
 *   context = {
 *     "node" = @ContextDefinition("entity:node", label = @Translation("Node"))
 *   }
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

    $building_id = 0;
    $room_id = 0;

    $node = \Drupal::routeMatch()->getParameter('node');

    if (!empty($node) && $node->hasField('field_room_building_id') && $node->hasField('field_room_room_id')) {
      $building_id = $node->get('field_room_building_id')?->first()?->getValue()["target_id"];
      $room_id = $node->get('field_room_room_id')?->first()?->getValue()["value"];
    }

    $cid = 'roomschedule_block:' . $building_id . ':' . $room_id;
    if ($cache = \Drupal::cache()->get($cid)) {
      $data = $cache->data;
    }
    else {
      // Grab MAUI room data.
      $maui_api = \Drupal::service('uiowa_maui.api');
      $data = $maui_api->getRoomSchedule(date('Y-m-d'), date('Y-m-d'), $building_id, $room_id);

      // Save data to cache.
      \Drupal::cache()->set($cid, $data, time() + 3600, ['roomschedule_block']);
    }

    if (!empty($data)) {
      $items = [];
      // Iterate through the data and push the values to the $items array.
      foreach ($data as $item) {
        $items[] = $item;
      }
      return [
        '#theme' => 'room_schedule_list',
        '#items' => $items,
        '#attached' => [
          'library' => [
            'classrooms_core/room_schedule_list',
          ],
        ],
      ];
    }
    else {
      return [
        '#prefix' => '<div class="room-schedule__no-results">',
        '#suffix' => '</div>',
        '#markup' => '<h2 class="h4 block__headline headline headline--serif headline--underline">Today\'s Schedule</h2><p class="room-schedule__no-results__message">There are no scheduled events for this room today.</p>',
      ];
    }
  }

}
