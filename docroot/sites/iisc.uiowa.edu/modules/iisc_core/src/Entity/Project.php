<?php

namespace Drupal\iisc_core\Entity;

use Drupal\uiowa_core\Entity\NodeBundleBase;
use Drupal\uiowa_core\Entity\RendersAsCardInterface;

/**
 * Provides an interface for IISC project entries.
 */
class Project extends NodeBundleBase implements RendersAsCardInterface {

  /**
   * {@inheritdoc}
   */
  public function buildCard(array &$build) {
    parent::buildCard($build);

    // Process additional card mappings.
    $this->mapFieldsToCardBuild($build, [
      '#content' => [
        'project_created_date',
        'field_academic_year',
        'field_project_partner',
        'body',
      ],
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
      'media_format' => 'media--widescreen',
      'card_media_position' => 'card--layout-left',
    ];
  }

}
