<?php

namespace Drupal\sitenow_p2lb;

use Drupal\node\Entity\Node;

/**
 * Batch process for cleaning up P2LB.
 */
class P2LbDeleteRevisions {

  /**
   * Delete v2 Page revisions.
   */
  public static function deleteRevisions(int $batch_id, array $nids, object &$context): void {
    $context['message'] = t('Batch @batch_id', [
      '@batch_id' => $batch_id,
    ]);
    foreach ($nids as $nid) {
      $node = Node::load($nid);
      $context['results'][] = $node->id();

      // Fetch revision ids.
      $vids = \Drupal::entityTypeManager()->getStorage('node')->revisionIds($node);

      // Get the protected revision.
      $protected_vid = $node->get('field_v3_conversion_revision_id')->value;

      if ($protected_vid) {
        foreach ($vids as $vid) {
          if ($vid <= $protected_vid) {
            // Built-in protection from deleting active revision.
            \Drupal::entityTypeManager()->getStorage('node')->deleteRevision($vid);
          }
        }
        \Drupal::logger('sitenow_p2lb')->notice('Deleted previous revisions for Node ID: ' . $nid);
      }
      else {
        \Drupal::logger('sitenow_p2lb')->notice('Whoa there! There is no protected revision for Node ID: ' . $nid);
      }
    }

  }

}
