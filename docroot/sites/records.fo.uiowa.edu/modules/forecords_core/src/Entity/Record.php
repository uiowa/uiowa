<?php

namespace Drupal\forecords_core\Entity;

use Drupal\uiowa_core\Entity\NodeBundleBase;
use Drupal\uiowa_core\Entity\RendersAsCardInterface;

/**
 * Provides an interface for record entities.
 */
class Record extends NodeBundleBase implements RendersAsCardInterface {

  /**
   * {@inheritdoc}
   */
  public function buildCard(array &$build) {
    parent::buildCard($build);

    // Process additional card mappings.
    $this->mapFieldsToCardBuild($build, [
      '#content' => [
        'body',
        'field_record_files_maintained_by',
        'field_record_category',
        'field_record_ui_retention_guide',
        'field_record_vital',
        'field_record_confidential',
      ],
    ]);
    $build['#link_indicator'] = TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultCardStyles(): array {
    return [
      ...parent::getDefaultCardStyles(),
    ];
  }

}
