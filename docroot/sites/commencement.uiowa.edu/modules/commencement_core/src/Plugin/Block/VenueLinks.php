<?php

namespace Drupal\commencement_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * A Venue links block.
 *
 * @Block(
 *   id = "venuelinks_block",
 *   admin_label = @Translation("Venue Links Block"),
 *   category = @Translation("Site custom")
 * )
 */
class VenueLinks extends BlockBase {

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
    $links = [
      'events' => 'Events',
      'map' => 'Map',
    ];

    $items = [];

    foreach ($links as $id => $title) {
      $options = [
        'attributes' => [
          'class' => [
            'bttn',
            'bttn--transparent',
            'bttn--small',
          ],
        ],
        'fragment' => $id,
      ];
      $url = Url::fromRoute('<current>', [], $options);
      $link = Link::fromTextAndUrl($title, $url)->toString();
      $items[] = $link;
    }

    $list = [
      '#theme' => 'item_list',
      '#items' => $items,
      '#wrapper_attributes' => ['class' => ['banner__action']],
      '#attributes' => [
        'class' => [
          'element--inline',
          'element--list-none',
          'element--margin-none',
          'element--center',
          'bttn--row',
          'bttn--full',
          'bttn--row--fixed',
          'block',
        ],
      ],
    ];

    $renderer = \Drupal::service('renderer');
    $list_rendered = $renderer->render($list);

    $nav = [
      '#type' => 'html_tag',
      '#tag' => 'nav',
      '#attributes' => [
        'role' => 'navigation',
      ],
      '#value' => $list_rendered,
    ];

    return $nav;
  }

}
