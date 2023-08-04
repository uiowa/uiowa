<?php

namespace Drupal\sitenow_p2lb\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountInterface;
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

    // Load the protected revision from the protected version id in that revision's values.
    $protected_revision_id = sitenow_p2lb_get_protected_revision_id($nid);

    // Guard against not finding the protected revision id.
    if (!$protected_revision_id) {
      return FALSE;
    }

    // Get the protected revision.
    /** @var NodeInterface $protected_revision */
    $protected_revision = sitenow_p2lb_get_protected_revision($protected_revision_id);

    // Guard against not finding the protected revision.
    if (!$protected_revision) {
      return FALSE;
    }

    // Get a string representation of the original revision's timestamp.
    $original_revision_timestamp = date('m/d/Y - h:i A', $protected_revision->getRevisionCreationTime());

    // Create a new revision.
    $protected_revision->setNewRevision();
    $protected_revision->isDefaultRevision(TRUE);

    // Add the message to the revision log.
    $protected_revision->revision_log = t(
      'This revision is a copy of the V2 version of this page from %date.',
      [
        '%date' => $original_revision_timestamp
      ]
    );

    // Set the user id for the revision.
    $protected_revision->setRevisionUserId(\Drupal::currentUser()->id());

    // Set the relevant revision timestamps.
    $protected_revision->setRevisionCreationTime(\Drupal::time()->getRequestTime());
    // Update changed time.
    $protected_revision->setChangedTime(\Drupal::time()->getRequestTime());
    // Not sure why this is necessary, but this appears to be a critical step
    // to ensure that the newly created revision registers as a revision in the
    // revision history and to prevent the reverted node from showing up in the
    // Converted list.
    $protected_revision->setRevisionTranslationAffected(TRUE);

    // Set moderation state.
    $protected_revision->set('moderation_state', 'published');

    // Save the revision for the node.
    $protected_revision->save();
  }

}
