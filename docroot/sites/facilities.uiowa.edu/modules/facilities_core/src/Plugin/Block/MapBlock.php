<?php

namespace Drupal\facilities_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * A Map block.
 *
 * @Block(
 *   id = "map_block",
 *   admin_label = @Translation("Map Block"),
 *   category = @Translation("Site custom"),
 *   context_definitions = {
 *     "node" = @ContextDefinition("entity:node", label = @Translation("Node"))
 *   }
 * )
 */
class MapBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $node = $this->getContextValue('node');

    $longitude = $node->get('field_building_longitude')->getString();
    $latitude = $node->get('field_building_latitude')->getString();
    $title = $node->get('title')->getString();

    if ($longitude && $latitude) {
      return [
        '#type' => 'inline_template',
        '#template' => '<div class="block media--type-remote-video block-facilities-core block-map-block"><iframe title="' . $title . ' Map" src="//uiadmin.maps.arcgis.com/apps/Embed/index.html?webmap=b8916ce59fb74e17893822f62b0db58c&center= ' . $longitude . ',' . $latitude . '&level=9&previewImage=false&scale=true&searchextent=false&disable_scroll=true&theme=light"></iframe></div>',
        '#attached' => [
          'library' => [
            'uids_base/remote-video',
          ],
        ],
      ];
    }
    return [];
  }

}
