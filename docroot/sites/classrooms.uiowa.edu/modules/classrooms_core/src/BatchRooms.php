<?php

namespace Drupal\classrooms_core;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\node\NodeInterface;

/**
 * Batch processes for importing rooms data.
 */
class BatchRooms {

  /**
   * Batch process callback.
   *
   * @param int $batch_id
   *   Id of the batch.
   * @param array $nodes
   *   Individual nodes to be processed.
   * @param object $room_processor
   *   The room processing object.
   * @param object $context
   *   Context for operations.
   */
  public static function processNode(int $batch_id, array $nodes, object $room_processor, object &$context): void {
    // Optional message displayed under the progressbar.
    $context['message'] = t('Running Batch "@id"', [
      '@id' => $batch_id,
    ]);

    foreach ($nodes as $node) {

      if (!$node instanceof FieldableEntityInterface) {
        continue;
      }
      if ($node instanceof NodeInterface) {
        $updated = $room_processor->process($node, $room_processor->getRecord($node));

        if ($updated === TRUE) {
          // Keep track of the updated.
          $context['results'][] = $node->id();
          $node->setSyncing(TRUE);
          $node->setNewRevision(TRUE);
          $node->revision_log = 'Updated room from source';
          $node->setRevisionCreationTime(\Drupal::time()->getRequestTime());
          $node->setRevisionUserId(1);
          $node->save();
        }
      }
    }
  }

  /**
   * Batch Finished callback.
   *
   * @param bool $success
   *   Success of the operation.
   * @param array $results
   *   Array of results for post processing.
   * @param array $operations
   *   Array of operations.
   */
  public static function processNodeFinished(bool $success, array $results, array $operations): void {
    $messenger = \Drupal::messenger();

    if ($success) {
      $messenger->addMessage(t('@count results updated. That is neat.', [
        '@count' => count($results),
      ]));
    }
  }

}
