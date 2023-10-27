<?php

namespace Drupal\grad_thesis_defense\Entity;

use Drupal\uiowa_core\Entity\NodeBundleBase;
use Drupal\uiowa_core\Entity\RendersAsCardInterface;

/**
 * Provides an interface for grad.uiowa.edu thesis defense entries.
 */
class ThesisDefense extends NodeBundleBase implements RendersAsCardInterface {

  /**
   * {@inheritdoc}
   */
  public function buildCard(array &$build) {
    parent::buildCard($build);

    // Add the person library.
    $build['#attached']['library'][] = 'uids_base/person';
    // Add the media library.
    $build['#attached']['library'][] = 'uids_base/media';

    // Handle the case when there is no image.
    if (empty($build['#media'])) {
      $build['#media']['empty'] = [
        '#markup' => '<div class="img--empty">&nbsp;</div>',
      ];
    }

    // Process additional card mappings.
    $this->mapFieldsToCardBuild($build, [
      '#subtitle' => 'field_thesis_defense_title',
      '#meta' => [
        'field_thesis_defense_date',
        'field_thesis_defense_location',
      ],
      '#content' => 'field_thesis_defense_chairs',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultCardStyles(): array {
    return [
      ...parent::getDefaultCardStyles(),
      'card_media_position' => 'card--layout-left',
      'border' => 'borderless',
      'headline_class' => 'headline--serif',
      'media_format' => 'media--circle media--border',
      'media_size' => 'media--small',
    ];
  }

}
