<?php

namespace Drupal\sitenow_p2lb\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Action to convert page nodes from Paragraphs to Layout Builder.
 *
 * @Action(
 *   id = "sitenow_p2lb_convert",
 *   label = @Translation("Convert V2 page to V3"),
 *   type = "node",
 *   confirm = TRUE,
 * )
 */
class ConvertParagraphsToLayoutBuilder extends ActionBase {

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
    /** @var \Drupal\sitenow_p2lb\P2LbConverter $converter */
    $node_storage = \Drupal::entityTypeManager()
      ->getStorage('node');

    // Get node id.
    $nid = $entity->get('nid')->getString();

//    // Get current revision ID.
//    $published_revision = $entity->getRevisionId();

    // Get latest revision ID.
    $latest_vid = $node_storage
      ->getLatestRevisionId($nid);

    // Load latest revision.
    $entity = $node_storage
      ->loadRevision($latest_vid);

//    // Duplicate latest revision
//    $new_revision = $node_storage->createRevision($entity);
//
//    // Set the new revision as a "Published".
//    $new_revision->set('moderation_state', 'published');
//
//    // Add a message to the revision log.
//    $new_revision->revision_log = 'Temporary revision';
//
//    // Set the user ID to the current user's ID for the revision.
//    $new_revision->setRevisionUserId(\Drupal::currentUser()->id());
//
//    // Set the relevant revision timestamps.
//    $new_revision->setRevisionCreationTime(\Drupal::time()->getRequestTime());
//    $new_revision->setChangedTime(\Drupal::time()->getRequestTime());
//
//    // Save the new revision.
//    $new_revision->save();
//
//    // Get the new latest revision ID.
//    $new_vid = $node_storage
//      ->getLatestRevisionId($nid);
//
//    // Load latest revision.
//    $entity = $node_storage
//      ->loadRevision($new_vid);

    $converter = \Drupal::service('sitenow_p2lb.converter_manager')->createConverter($entity);

    $converter->convert();

//    // Set back to original published revision
//    $node_storage->loadRevision($published_revision);
//
//    // Delete that new published revision we made
//    $revision_to_delete = $new_vid;
//    $node_storage->deleteRevision($revision_to_delete);

  }

}
