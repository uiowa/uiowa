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
      '#subtitle' => [
        'field_writer_bio_family_name',
        'field_writer_bio_given_name',
      ],
      '#content' => [
        'field_writer_bio_sample',
        'field_writer_bio_sample_original',
      ],
    ]);

  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultCardStyles(): array {
    return [
      ...parent::getDefaultCardStyles(),
      'media_format' => 'media--circle media--border',
      'border' => 'borderless',
    ];
  }

}
