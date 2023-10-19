<?php

namespace Drupal\uiowa_area_of_study\Entity;

use Drupal\uiowa_core\Entity\NodeBundleBase;
use Drupal\uiowa_core\Entity\RendersAsCardInterface;

/**
 * Provides an interface for area of study entries.
 */
class AreaOfStudy extends NodeBundleBase implements RendersAsCardInterface {

  /**
   * If entity has link directly to source field.
   *
   * @var string|null
   *   field name or null.
   */
  protected ?string $sourceLinkDirect = 'field_area_of_study_link_direct';

  /**
   * If entity has source link field.
   *
   * @var string|null
   *   field name or null.
   */
  protected ?string $sourceLink = 'field_area_of_study_source_link';

  /**
   * {@inheritdoc}
   */
  public function buildCard(array &$build) {
    parent::buildCard($build);

    // Handle link directly to source functionality.
    $build['#url'] = $this->getNodeUrl();

    $build['#title_heading_size'] = 'h3';

  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultCardStyles(): array {
    return [
      ...parent::getDefaultCardStyles(),
      'card_headline_style' => '',
    ];
  }

}
