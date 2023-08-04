<?php

namespace Drupal\sitenow_p2lb\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\node\NodeStorageInterface;
use Drupal\sitenow_p2lb\P2LbHelper;
use Drupal\sitenow_pages\Entity\Page;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for P2LB routes.
 */
class P2LbController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The entity repository service.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * Constructs a NodeController object.
   *
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   */
  public function __construct(DateFormatterInterface $date_formatter, RendererInterface $renderer, EntityRepositoryInterface $entity_repository) {
    $this->dateFormatter = $date_formatter;
    $this->renderer = $renderer;
    $this->entityRepository = $entity_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('date.formatter'),
      $container->get('renderer'),
      $container->get('entity.repository')
    );
  }

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
      if (is_numeric($node->field_v3_conversion_revision_id?->value)) {
        $build['done'] = [
          '#markup' => $this->t('<p>This page has been converted.</p>'),
        ];
      }
      else {
        $build['not_done'] = [
          '#markup' => $this->t('<p>This page has not been converted yet. You can convert it using the @link.</p>', [
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
            '#markup' => $this->t('<p>This content is ready to be converted and we do not expect any issues.</p>'),
          ];
        }
      }
    }
    else {
      $build['not_applicable'] = [
        '#markup' => $this->t('<p>The @type content type does not need to be converted to V3.</p>', [
          '@type' => $node->getType(),
        ]),
      ];
    }

    return $build;
  }

  /**
   * Gets a list of node revision IDs for a specific node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   * @param \Drupal\node\NodeStorageInterface $node_storage
   *   The node storage handler.
   *
   * @return int[]
   *   Node revision IDs (in descending order).
   */
  protected function getRevisionIds(NodeInterface $node, NodeStorageInterface $node_storage) {
    $result = $node_storage->getQuery()
      ->accessCheck(TRUE)
      ->allRevisions()
      ->condition($node->getEntityType()->getKey('id'), $node->id())
      ->sort($node->getEntityType()->getKey('revision'), 'DESC')
      ->pager(50)
      ->execute();
    return array_keys($result);
  }

}
