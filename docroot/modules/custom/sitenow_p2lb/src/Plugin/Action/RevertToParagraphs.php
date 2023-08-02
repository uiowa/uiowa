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

    // Get the node id.
    $nid = $entity->id() ?? NULL;

    // Load the protected revision from the protected version id in that revision's values.
    $protected_revision_id = sitenow_p2lb_get_protected_revision_id($nid);

    // Guard against not finding the protected revision id.
    if (!$protected_revision_id) {
      return FALSE;
    }

    // Get the protected revision.
    $protected_revision = sitenow_p2lb_get_protected_revision($protected_revision_id);

    // Guard against not finding the protected revision.
    if (!$protected_revision) {
      return FALSE;
    }

    // Get a string representation of the original revision's timestamp.
    $original_revision_timestamp = date('m/d/Y - h:i A', $protected_revision->getRevisionCreationTime());

    // Guard against not finding the original revision timestamp.
    if (!$original_revision_timestamp) {
      return FALSE;
    }

    // Set a new revision for the node.
    sitenow_p2lb_set_new_revision(
      $protected_revision,
      t(
        'This revision is a copy of the V2 version of this page from %date.',
        ['%date' => $original_revision_timestamp]
      ),
      TRUE
    );
  }

}
