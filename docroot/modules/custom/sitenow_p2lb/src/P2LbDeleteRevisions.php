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
  public static function deleteRevisions(int $batch_id, array $nids, array &$context) {
    $context['message'] = t('Batch @batch_id', [
      '@batch_id' => $batch_id,
    ]);
    foreach ($nids as $nid) {
      $node = Node::load($nid);
      $context['results'][] = $node->id();

      $node_storage = \Drupal::entityTypeManager()->getStorage('node');

      // Fetch revision ids.
      $vids = $node_storage->revisionIds($node);

      // Get the protected revision.
      $protected_vid = $node->get('field_v3_conversion_revision_id')->value;

      // If this is a new node since P2LB, skip it.
      if ($protected_vid === 'v3_new') {
        continue;
      }

      if ($protected_vid) {
        foreach ($vids as $vid) {
          if ($vid <= $protected_vid) {
            // Built-in protection from deleting active revision.
            $node_storage->deleteRevision($vid);
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
