<?php

namespace Drupal\uipress_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * An Add to Cart button block.
 *
 * @Block(
 *   id = "addtocartbutton_block",
 *   admin_label = @Translation("Add to Cart Block"),
 *   category = @Translation("Site custom")
 * )
 */
class AddToCartButton extends BlockBase {

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
    $pid = $node->get('field_book_type')->getValue()[0]['target_id'];
    $paragraph = \Drupal\paragraphs\Entity\Paragraph::load( $pid );
    $isbn = $paragraph->get('field_book_isbn')->getValue()[0]['value'];
    $href = 'https://cdcshoppingcart.uchicago.edu/Cart/ChicagoBook.aspx?ISBN=' . $isbn . '&PRESS=iowa';
    $markup = '<div class="layout-builder-block">
        <a class="bttn bttn--full bttn--primary bttn--caps" href=' . $href . '>
            Add to Cart <span class="fa-arrow-right fas"></span>
        </a>
    </div>';

    return ['#markup' => $markup];
  }

}
