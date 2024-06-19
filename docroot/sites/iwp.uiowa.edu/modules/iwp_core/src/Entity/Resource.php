<?php

namespace Drupal\iwp_core\Entity;

use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\uiowa_core\Entity\NodeBundleBase;
use Drupal\uiowa_core\Entity\RendersAsCardInterface;

/**
 * Provides an interface for resource entities.
 */
class Resource extends NodeBundleBase implements RendersAsCardInterface {

  /**
   * {@inheritdoc}
   */
  public function buildCard(array &$build) {
    parent::buildCard($build);

    $this->mapFieldsToCardBuild($build, [
      '#meta' => [
        'field_resource_type',
      ]
    ]);
    
//    // Combine fields into an unordered list.
//    $items = [];
//    $list_fields = [
//      'field_writer_bio_sample',
//      'field_writer_bio_sample_original',
//      'field_writer_bio_media_link',
//    ];
//    foreach ($list_fields as $field) {
//      if ($this->hasField($field) && !$this->$field->isEmpty()) {
//        if ($field === 'field_writer_bio_media_link') {
//          $links = $this->get($field)->getValue();
//          foreach ($links as $link) {
//            $items[] = Link::fromTextAndUrl($link['title'], Url::fromUri($link['uri']));
//          }
//        }
//        else {
//          $items[] = $this->$field->view('teaser');
//        }
//
//      }
//    }
//    if (!empty($items)) {
//      $build['#content']['related_links'] = [
//        '#theme' => 'item_list',
//        '#type' => 'ul',
//        '#items' => $items,
//        '#weight' => 3,
//      ];
//    }

  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultCardStyles(): array {
    return [
      ...parent::getDefaultCardStyles(),
//      'media_format' => 'media--square',
//      'border' => 'borderless',
    ];
  }

}
