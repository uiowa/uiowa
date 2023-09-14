<?php

namespace Drupal\sitenow_microsite\Controller;

use Drupal\Core\Controller\ControllerBase;
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
    $node_storage = $this->entityTypeManager()->getStorage('node');
    $node = $node_storage->load($nid);
    foreach ($node->get('field_microsite_subpages') as $paragraph) {
      if ($paragraph->entity->getType() == 'subpage') {
        $subpage = $paragraph->entity;
        if (!empty($subpage)) {
          $path = $subpage->field_subpage_slug->value;
          if ($page == $path) {
            $build['content']['title'] = [
              '#type' => 'item',
              '#markup' => '<h2>' . $subpage->field_subpage_title->value . '</h2>',
            ];
            $build['content']['paragraph'] = $paragraph->view();
          }
        }
      }
    }
    if (!empty($build)) {
      return $build;
    }
    else {
      throw new NotFoundHttpException();
    }
  }

}
