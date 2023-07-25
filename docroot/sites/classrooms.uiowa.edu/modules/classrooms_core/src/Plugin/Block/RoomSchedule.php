<?php

namespace Drupal\classrooms_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Pager\PagerManager;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\uiowa_maui\MauiApi;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A 'Room Schedule' block.
 *
 * @Block(
 *   id = "roomschedule_block",
 *   admin_label = @Translation("Room Schedule"),
 *   category = @Translation("Room"),
 * )
 */
class RoomSchedule extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The current route match service.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $currentRouteMatch;

  /**
   * The current pager service.
   *
   * @var \Drupal\Core\Pager\PagerManager
   */
  protected $pagerManager;

  /**
   * The uiowa_maui.api service.
   *
   * @var \Drupal\uiowa_maui\MauiApi
   */
  protected $mauiApi;

  /**
   * Constructs a new ThemeTestSubscriber.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Routing\CurrentRouteMatch $currentRouteMatch
   *   The current route match handler.
   * @param \Drupal\Core\Pager\PagerManager $pagerManager
   *   The pager manager service.
   * @param \Drupal\uiowa_maui\MauiApi $mauiApi
   *   The uiowa_maui.api service.
   */
  public function __construct(array $configuration, string $plugin_id, mixed $plugin_definition, CurrentRouteMatch $currentRouteMatch, PagerManager $pagerManager, MauiApi $mauiApi) {
    $this->currentRouteMatch = $currentRouteMatch;
    $this->pagerManager = $pagerManager;
    $this->mauiApi = $mauiApi;
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('pager.manager'),
      $container->get('uiowa_maui.api'),
    );
  }

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
    $node = $this->currentRouteMatch->getParameter('node');

    if (!is_null($node)) {
      $building_id = $node
        ->field_room_building_id
        ?->target_id;
      $room_id = $node
        ->field_room_room_id
        ?->value;

      if (!is_null($building_id) && !is_null($room_id)) {
        // Grab MAUI room data.
        $data = $this->mauiApi->getRoomSchedule(date('Y-m-d'), date('Y-m-d'), $building_id, $room_id);
        static $element = 0;

        if (!empty($data)) {

          $count = count($data);
          $element++;
          $length = 4;
          $current = $this->pagerManager->createPager($count, $length, $element)->getCurrentPage();
          $offset = $current * $length;
          $data = array_slice($data, $offset, $length);

          $items = [];

          // Iterate through the data and push the values to the $items array.
          foreach ($data as $item) {
            $items[] = $item;
          }

          $build = [
            'header' => [
              '#markup' => '<h2 class="h4 block__headline headline headline--serif headline--underline">Today\'s Schedule</h2>',
            ],
            'container' => [
              '#type' => 'container',
              '#attributes' => ['class' => ['list-container__inner']],
              '#cache' => [
                'tags' => ['time:hourly'],
                'max-age' => 60,
              ],
            ],
          ];

          // Iterate through the $items array.
          foreach ($items as $item) {
            $attributes = [];
            $attributes['class'] = [
              'card--layout-right',
              'borderless',
              'headline--serif',
            ];

            $build['container']['schedule'][] = [
              '#type' => 'card',
              '#attributes' => $attributes,
              '#subtitle' => $item->startTime . ' - ' . $item->endTime,
              '#meta' => $item->activity,
              '#title' => $item->title,
              '#title_heading_size' => 'h3',
            ];
          }
          $build['content']['pager'] = [
            '#type' => 'pager',
            '#element' => $element,
            '#quantity' => 3,
          ];
        }
        else {
          $build = [
            '#prefix' => '<div class="room-schedule__no-results">',
            '#suffix' => '</div>',
            '#markup' => '<h2 class="h4 block__headline headline headline--serif headline--underline">Today\'s Schedule</h2><p class="room-schedule__no-results__message">There are no scheduled events for this room today.</p>',
          ];
        }

        // Set the cache metadata.
        $cache = new CacheableMetadata();
        $cache->setCacheTags(['time:hourly']);
        $cache->applyTo($build);
      }
    }

    return $build;
  }

}
