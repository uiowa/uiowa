<?php

namespace Drupal\sitenow_p2lb\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;

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

  use StringTranslationTrait;

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
  public function execute(NodeInterface $entity = NULL) {

    // Get the node id.
    $nid = $entity->id();

    // Get the protected revision.
    /** @var \Drupal\node\NodeInterface $protected_revision */
    $protected_revision = sitenow_p2lb_get_protected_revision($nid);

    // Guard against not finding the protected revision.
    if (!$protected_revision) {
      return FALSE;
    }

    // Get a string representation of the original revision's timestamp.
    $original_revision_timestamp = date('m/d/Y - h:i A', $protected_revision->getRevisionCreationTime());

    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $node_storage */
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');

    // Create a new revision from node storage.
    $new_revision = $node_storage->createRevision($protected_revision);

    // Add the message to the revision log.
    $new_revision->revision_log = $this->t(
      'This is a copy of last version of the page before it was first converted to V3 on %date.',
      [
        '%date' => $original_revision_timestamp,
      ]
    );

    // Set the user ID to the current user's ID for the new revision.
    $new_revision->setRevisionUserId(\Drupal::currentUser()->id());

    // Set the relevant revision timestamps.
    $new_revision->setRevisionCreationTime(\Drupal::time()->getRequestTime());
    $new_revision->setChangedTime(\Drupal::time()->getRequestTime());

    // Save the new revision.
    $new_revision->save();

    // Finally, clear the tempstore.
    sitenow_p2lb_clear_tempstore($new_revision);
  }

}
