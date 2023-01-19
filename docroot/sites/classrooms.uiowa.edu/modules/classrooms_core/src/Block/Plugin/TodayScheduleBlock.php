<?php

namespace Drupal\uiowa_maui\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilderInterface;
// Use Drupal\Core\Url;
// use Drupal\link\Plugin\Field\FieldWidget\LinkWidget;
// use Drupal\uiowa_core\HeadlineHelper;
// use Drupal\uiowa_core\LinkHelper;.
use Drupal\uiowa_maui\MauiApi;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A Request Info button block.
 *
 * @Block(
 *   id = "todayschedule_block",
 *   admin_label = @Translation("Today's Schedule"),
 *   category = @Translation("Site custom")
 * )
 */
class TodayScheduleBlock extends BlockBase {
  /**
   * The MAUI API service.
   *
   * @var \Drupal\uiowa_maui\MauiApi
   */

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['label_display' => FALSE];
  }

  /**
   * The form_builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Override the construction method.
   *
   * @param array $configuration
   *   The block configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\uiowa_maui\MauiApi $maui
   *   The uiowa_maui.api service.
   * @param \Drupal\Core\Form\FormBuilderInterface $formBuilder
   *   The form_builder service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MauiApi $maui, FormBuilderInterface $formBuilder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->maui = $maui;
    $this->formBuilder = $formBuilder;
  }

  /**
   * Override the create method.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The application container.
   * @param array $configuration
   *   The block configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   *
   * @return static
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('uiowa_maui.api'),
     $container->get('form_builder')
    );
  }

  /**
   * Return the schedule for a classroom for a date range.
   *
   * GET /pub/registrar/courses/AstraRoomSchedule/{startDate}/{endDate}/{bldgCode}/{roomNumber}.
   *
   * @param string $startdate
   *   Date formated as YYYY-MM-DD.
   * @param string $enddate
   *   Date formated as YYYY-MM-DD.
   * @param string $building_id
   *   The building code needs to match the code as it is entered in Astra.
   * @param string $room_id
   *   The room number needs to match the code as it is entered in Astra.
   *
   * @return array
   *   JSON decoded array of response data.
   */
  public function getRoomSchedule($startdate, $enddate, $building_id, $room_id) {
    return $this->request('GET', '/pub/registrar/courses/AstraRoomSchedule/' . $startdate . '/' . $enddate . '/' . $building_id . "/" . $room_id);
  }

  /**
   * Build the block.
   */
  public function build() {
    // $config = $this->getConfiguration();
    $build = [
      '#theme' => 'requestinfobutton_block',
      '#attached' => [
        'library' => 'uiowa_maui/session_dates',
      ],
    ];
    return $build;
  }

}
