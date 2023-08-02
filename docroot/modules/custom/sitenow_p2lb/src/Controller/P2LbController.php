<?php

namespace Drupal\sitenow_p2lb\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Drupal\sitenow_p2lb\P2LbHelper;
use Drupal\sitenow_pages\Entity\Page;

/**
 * Returns responses for P2LB routes.
 */
class P2LbController extends ControllerBase {

  /**
   * Generates a status report for converting a node to V3.
   *
   * @param \Drupal\node\NodeInterface $node
   *   A node object.
   *
   * @return array
   *   An array as expected by \Drupal\Core\Render\RendererInterface::render().
   */
  public function status(NodeInterface $node) {
    $build['#title'] = $this->t('V3 Conversion status for %title', ['%title' => $node->label()]);

    if ($node instanceof Page) {
      $issues = P2LbHelper::analyzeNode($node);

      if (!empty($issues)) {
        $build['issues'] = [
          '#type' => 'container',
          '#weight' => -10,
        ];

        $build['issues']['list'] = [
          '#theme' => 'item_list',
          '#items' => $issues,
        ];
      }
      else {
        $build['no_worries'] = [
          '#markup' => $this->t('This content is ready to be converted and we do not expect any issues.'),
        ];
      }
    }
    else {
      $build['not_applicable'] = [
        '#markup' => $this->t('The @type content type does not need to be converted to V3.', [
          '@type' => $node->getType(),
        ]),
      ];
    }

    return $build;
  }

}
