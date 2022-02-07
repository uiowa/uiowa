<?php

namespace Drupal\uipress_core\Plugin\Block;

use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Core\Block\BlockBase;

/**
 * Cart buttons block.
 *
 * @Block(
 *   id = "cartbuttons_block",
 *   admin_label = @Translation("Cart Buttons Block"),
 *   category = @Translation("Site custom")
 * )
 */
class CartButtons extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['label_display' => FALSE];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $node = \Drupal::routeMatch()->getParameter('node');
    $href = '';
    if ($node) {
      $pid = $node->get('field_book_type')->getValue()[0]['target_id'];
      $paragraph = Paragraph::load($pid);
      $isbn = $paragraph->get('field_book_isbn')->getValue()[0]['value'];
      $href = 'https://cdcshoppingcart.uchicago.edu/Cart/ChicagoBook.aspx?ISBN=' . $isbn . '&PRESS=iowa';
    }

    return ['#href' => $href];
  }

}
