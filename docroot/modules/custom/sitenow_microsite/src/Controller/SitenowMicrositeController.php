<?php

namespace Drupal\sitenow_microsite\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Returns responses for SiteNow Microsite routes.
 */
class SitenowMicrositeController extends ControllerBase {

  /**
   * Builds the response.
   *
   * @param int $nid
   *   The nid.
   * @param string $page
   *   The path of custom page.
   *
   * @return array
   *   A renderable array for the page.
   */
  public function build($nid, $page): array {
    $node = Node::load($nid);
    $paths = [];
    $title = $this->t('This works!');
    $body = '';
    foreach ($node->get('field_microsite_subpages') as $paragraph) {
      if ($paragraph->entity->getType() == 'subpage') {
        $subpage = $paragraph->entity;
        if (!empty($subpage)) {
          $path = $subpage->field_subpage_slug->value;
          if ($page == $path) {
            $title = $subpage->field_subpage_title->value;
            $body = $subpage->field_subpage_body->view('full');
          }
          $paths[] = $path;
        }
      }
    }
    if (in_array($page, $paths)) {
      $build['content']['title'] = [
        '#type' => 'item',
        '#markup' => '<h2>' . $title . '</h2>',
      ];
      $build['content']['body'] = $body;
      return $build;
    }
    else {
      throw new NotFoundHttpException();
    }
  }

}
