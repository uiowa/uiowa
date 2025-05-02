<?php

namespace Drupal\sitenow_signage\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * A Uiowa Logo block.
 *
 * @Block(
 *   id = "iowalogo_block",
 *   admin_label = @Translation("Iowa Logo Block"),
 *   category = @Translation("Site custom")
 * )
 */
class IowaLogo extends BlockBase {

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
    return [
      '#type' => 'inline_template',
      '#template' => '
        {% include "@uids_base/uids/logo.twig" with {
          path: "https://uiowa.edu",
          logo_classes: "logo--tab",
          logo_path_png: logo_path,
          logo_id: "header"
        } %}
        ',
    ];
  }

}
