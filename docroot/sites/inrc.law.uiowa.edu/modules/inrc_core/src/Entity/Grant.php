<?php

namespace Drupal\inrc_core\Entity;

use Drupal\uiowa_core\Entity\NodeBundleBase;
use Drupal\uiowa_core\Entity\RendersAsCardInterface;

/**
 * Provides an interface for grant entities.
 */
class Grant extends NodeBundleBase implements RendersAsCardInterface {

  /**
   * {@inheritdoc}
   */
  public function buildCard(array &$build) {
    parent::buildCard($build);

    // Process additional card mappings.
    $this->mapFieldsToCardBuild($build, [
      '#meta' => 'field_grant_application_deadline',
      '#content' => [
        'body',
        'field_grant_application_info',
      ],
    ]);
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
