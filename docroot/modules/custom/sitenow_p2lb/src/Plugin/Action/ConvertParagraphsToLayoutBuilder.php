<?php

namespace sitenow_p2lb\Plugin\Action;

use Drupal\Core\Session\AccountInterface;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;

/**
 * Action to convert page nodes from Paragraphs to Layout Builder.
 *
 * @Action(
 *   id = "sitenow_p2lb_convert_paragraphs_to_layout_builder",
 *   label = @Translation("Convert Paragraphs to Layout Builder"),
 *   type = "",
 *   confirm = TRUE,
 *   requirements = {
 *     "_permission" = "some permission",
 *     "_custom_access" = TRUE,
 *   },
 * )
 */
class ConvertParagraphsToLayoutBuilder extends ViewsBulkOperationsActionBase {

  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    // TODO: Implement access() method.
  }

  public function execute() {
    // TODO: Implement execute() method.
  }
}
