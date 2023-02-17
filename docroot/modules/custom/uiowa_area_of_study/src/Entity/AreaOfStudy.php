<?php

namespace Drupal\uiowa_area_of_study\Entity;

use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\uiowa_core\Entity\NodeBundleBase;
use Drupal\uiowa_core\Entity\TeaserCardInterface;

/**
 * Provides an interface for article entries.
 */
class AreaOfStudy extends NodeBundleBase implements TeaserCardInterface {

  /**
   * If entity has link directly to source field.
   *
   * @var string|null
   *   field name or null.
   */
  protected $source_link_direct = 'field_area_of_study_link_direct';

  /**
   * If entity has source link field.
   *
   * @var string|null
   *   field name or null.
   */
  protected $source_link = 'field_area_of_study_source_link';

  /**
   * {@inheritdoc}
   */
  public function buildCard(array &$build) {
    parent::buildCard($build);

    // Handle link directly to source functionality.
    $build['#url'] = $this->getNodeUrl();

  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultCardStyles(): array {
    $default_classes = [
      ...parent::getDefaultCardStyles(),
      'card_media_position' => 'card--layout-left',
      'media_border' => 'media--border',
      'media_format' => 'media--circle',
      'media_size' => 'media--small',
    ];

    return $default_classes;
  }

}
