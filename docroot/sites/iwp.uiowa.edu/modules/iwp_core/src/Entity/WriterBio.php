<?php

namespace Drupal\iwp_core\Entity;

use Drupal\uiowa_core\Entity\NodeBundleBase;
use Drupal\uiowa_core\Entity\RendersAsCardInterface;

/**
 * Provides an interface for writer bio page entries.
 */
class WriterBio extends NodeBundleBase implements RendersAsCardInterface {

  /**
   * {@inheritdoc}
   */
  public function buildCard(array &$build) {
    parent::buildCard($build);

    $this->mapFieldsToCardBuild($build, [
      '#meta' => [
        'field_writer_bio_session_status',
        'field_writer_bio_languages',
        'field_writer_bio_countries',
      ],
      '#content' => [
        'field_writer_bio_media_link',
      ],
    ]);

    // Combine fields into an unordered list.
    $items = [];
    $list_fields = [
      'field_writer_bio_sample',
      'field_writer_bio_sample_original',
    ];
    foreach ($list_fields as $field) {
      if ($this->hasField($field) && !$this->$field->isEmpty()) {
        $items[] = $this->$field->view('teaser');
      }
    }
    if (!empty($items)) {
      $build['#content']['samples'] = [
        '#theme' => 'item_list',
        '#type' => 'ul',
        '#items' => $items,
        '#weight' => 3,
      ];
    }

  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultCardStyles(): array {
    return [
      ...parent::getDefaultCardStyles(),
      'media_format' => 'media--square media--border',
      'border' => 'borderless',
    ];
  }

}
