<?php

namespace Drupal\housing_core\Entity;

use Drupal\paragraphs\Entity\Paragraph;
use Drupal\uiowa_core\Entity\RendersAsCardInterface;
use Drupal\uiowa_core\Entity\RendersAsCardTrait;

/**
 * Provides an interface for paragraph ctas on housing entries.
 */
class HousingCTA extends Paragraph implements RendersAsCardInterface {

  use RendersAsCardTrait;

  /**
   * {@inheritdoc}
   */
  public function buildCard(array &$build) {
    $this->buildCardStyles($build);

    // Process additional card mappings.
    $this->mapFieldsToCardBuild($build, [
      '#content' => 'field_housing_cta_description',
      '#media' => 'field_housing_cta_image',
      '#title' => '	field_housing_cta_title',
    ]);

    $build['#url'] = $this->get('field_housing_cta_link')?->get(0)?->getUrl()?->toString();
    $build['#link_indicator'] = TRUE;

  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultCardStyles(): array {
    return [
      'headline_class' => 'headline--serif',
      'styles' => 'bg--white',
    ];
  }

}
