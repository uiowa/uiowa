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
    $converter = \Drupal::service('sitenow_p2lb.converter_manager')->createConverter($entity);

    $converter->convert();
  }

}
