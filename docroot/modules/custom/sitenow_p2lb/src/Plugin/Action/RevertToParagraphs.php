<?php

namespace Drupal\sitenow_p2lb\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Action to revert page nodes back to Paragraphs.
 *
 * @Action(
 *   id = "sitenow_p2lb_revert",
 *   label = @Translation("Revert from V3 to V2"),
 *   type = "node",
 *   confirm = TRUE,
 * )
 */
class RevertToParagraphs extends ActionBase {

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    /** @var \Drupal\node\NodeInterface $object */
    $result = $object->access('update', $account, TRUE)
      ->andIf($object->field_page_content_block->access('edit', $account, TRUE));

    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(ContentEntityInterface $entity = NULL) {
    $nid = $entity->get('nid')?->getValue()[0]['value'] ?? NULL;
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');

    if (!$nid) {
      return;
    }

    $most_recent_revision_id = $node_storage?->getLatestRevisionId($nid);

    // Get the most recent revision.
    $most_recent_revision = $node_storage?->loadRevision($most_recent_revision_id);

    // Guard against not finding the most recent revision.
    if (!$most_recent_revision) {
      return;
    }

    // Get protected revision id from that revision's values.
    $protected_revision_id = $most_recent_revision->field_v3_conversion_revision_id?->value;

    // Guard against not finding the protected revision id.
    if (!$protected_revision_id) {
      return;
    }

    $foo = $entity;
  }

}

















