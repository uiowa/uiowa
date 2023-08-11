<?php

namespace Drupal\sitenow_p2lb\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Node view mode for visualizing V3 conversion issues.
 *
 * @Block(
 *   id = "p2lb_v3_status",
 *   admin_label = @Translation("P2lb V3 Status"),
 *   category = @Translation("Restricted")
 * )
 */
class P2lbV3Status extends BlockBase {



  public function build() {
    $node = \Drupal::routeMatch()->getParameter('node');
    $nid = NULL;
    if ($node instanceof \Drupal\node\NodeInterface) {
      // You can get nid and anything else you need from the node object.
      $nid = $node->id();
    }

    return [
      '#type' => 'markup',
      '#markup' => $nid !== NULL ?
        'this is the V3 status custom block of node' . $nid . '.'
        :
        NULL,
    ];
  }
}
