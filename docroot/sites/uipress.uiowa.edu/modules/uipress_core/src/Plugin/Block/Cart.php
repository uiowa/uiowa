<?php

namespace Drupal\uipress_core\Plugin\Block;

use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Core\Block\BlockBase;

/**
 * Cart buttons block.
 *
 * @Block(
 *   id = "cart_block",
 *   admin_label = @Translation("Cart Block"),
 *   category = @Translation("Site custom")
 * )
 */
class Cart extends BlockBase {

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
      $markup = '<div class="bg--white">
      <a class="bttn bttn--outline bttn--sans-serif" href="https://cdcshoppingcart.uchicago.edu/Cart/Cart?PRESS=iowa">View Cart <span class="fas fa-shopping-cart"></span></a></div>';
      return ['#markup' => $markup];
  }

}
