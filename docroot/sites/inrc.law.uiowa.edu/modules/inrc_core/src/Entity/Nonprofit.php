<?php

namespace Drupal\inrc_core\Entity;

use Drupal\uiowa_core\Entity\NodeBundleBase;
use Drupal\uiowa_core\Entity\RendersAsCardInterface;

/**
 * Provides an interface for nonprofit entities.
 */
class Nonprofit extends NodeBundleBase implements RendersAsCardInterface {

  /**
   * {@inheritdoc}
   */
  public function buildCard(array &$build) {
    parent::buildCard($build);

    // Process additional card mappings.
    $this->mapFieldsToCardBuild($build, [
      '#subtitle' => [
        'field_np_contact_name',
        'field_np_contact_title',
      ],
      '#meta' => [
        'field_np_email',
        'field_np_telephone_number',
        'field_np_address',
        'field_np_website',
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
