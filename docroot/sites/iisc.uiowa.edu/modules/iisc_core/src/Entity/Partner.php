<?php

namespace Drupal\iisc_core\Entity;

use Drupal\uiowa_core\Entity\NodeBundleBase;
use Drupal\uiowa_core\Entity\RendersAsCardInterface;

/**
 * Provides an interface for IISC partner entries.
 */
class Partner extends NodeBundleBase implements RendersAsCardInterface {

  /**
   * {@inheritdoc}
   */
  public function buildCard(array &$build) {
    parent::buildCard($build);

    // Process additional card mappings.
    $this->mapFieldsToCardBuild($build, [
      '#content' => ['body', 'field_project_partner'],
    ]);

    // Update to set the link indicator, otherwise
    // it will use the site-wide teaser setting.
    $build['#link_indicator'] = TRUE;

  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultCardStyles(): array {
    return [
      ...parent::getDefaultCardStyles(),
      'media_size' => 'media--small',
      'media_format' => 'media--square',
      'card_media_position' => 'card--layout-left',
    ];
  }

}
