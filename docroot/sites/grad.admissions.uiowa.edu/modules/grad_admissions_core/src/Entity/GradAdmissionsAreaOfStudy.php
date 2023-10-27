<?php

namespace Drupal\grad_admissions_core\Entity;

use Drupal\uiowa_core\Entity\NodeBundleBase;
use Drupal\uiowa_core\Entity\RendersAsCardInterface;

/**
 * Provides an interface for areas of study page entries.
 */
class GradAdmissionsAreaOfStudy extends NodeBundleBase implements RendersAsCardInterface {

  /**
   * {@inheritdoc}
   */
  public function buildCard(array &$build) {
    parent::buildCard($build);

    $build['#attached']['library'][] = 'grad_admissions_core/area-of-study';

    // Process additional card mappings.
    $this->mapFieldsToCardBuild($build, [
      '#content' => 'body',
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
