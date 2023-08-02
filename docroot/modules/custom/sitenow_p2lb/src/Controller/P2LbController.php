<?php

namespace Drupal\sitenow_p2lb\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
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
      if (is_numeric($node->field_v3_conversion_revision_id->value)) {
        $build['done'] = [
          '#markup' => $this->t('This page has been converted.'),
        ];
      }
      else {
        $build['not_done'] = [
          '#markup' => $this->t('This page has not been converted yet. You can convert it using the @link.', [
            '@link' => Link::fromTextAndUrl('SiteNow Converter tool', Url::fromRoute('sitenow_p2lb.content_converter'))->toString(),
          ]),
          '#weight' => 0,
        ];
        $issues = P2LbHelper::analyzeNode($node);

        if (!empty($issues)) {
          $build['issues'] = [
            '#type' => 'container',
            '#weight' => 1,
          ];

          $build['issues']['title'] = [
            '#markup' => $this->t('<h2>Conversion Issues</h2><p>The following issues will not prevent this page from being converted to V3, but may result in a degraded version of the content. For more information, please refer to the <a href="https://sitenow.uiowa.edu/documentation/sitenow-v2-v3-conversion">related documentation</a>.</p>'),
          ];

          $report_issues = [];

          foreach ($issues as $issue => $count) {
            $report_issues[] = "{$this->t($issue)} ($count)";
          }

          $build['issues']['list'] = [
            '#theme' => 'item_list',
            '#items' => $report_issues,
          ];
        }
        else {
          $build['no_worries'] = [
            '#markup' => $this->t('This content is ready to be converted and we do not expect any issues.'),
          ];
        }
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
