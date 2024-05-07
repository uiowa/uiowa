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
    return ['label_display' => FALSE];
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
      $url = Url::fromRoute('<current>', [], ['fragment' => $id]);
      $link = Link::fromTextAndUrl($title, $url);
      $items[] = $link;
    }

    $list = [
      '#theme' => 'item_list',
      '#items' => $items,
      '#wrapper_attributes' => ['class' => ['menu-wrapper', 'menu-wrapper--horizontal', 'menu--main']],
      '#attributes' => ['class' => ['menu']],
    ];

    $renderer = \Drupal::service('renderer');
    $list_rendered = $renderer->render($list);

    $nav = [
      '#type' => 'html_tag',
      '#tag' => 'nav',
      '#attributes' => [
        'role' => 'navigation',
        'aria-labelledby' => '-menu',
      ],
      '#value' => $list_rendered,
    ];

    return $nav;
  }

}
