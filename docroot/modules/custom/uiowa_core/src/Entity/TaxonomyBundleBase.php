<?php

namespace Drupal\uiowa_core\Entity;

use Drupal\Core\Url;
use Drupal\taxonomy\Entity\Term;

/**
 * Bundle-specific subclass of Node.
 */
abstract class TaxonomyBundleBase extends Term implements RendersAsCardInterface {

  use RendersAsCardTrait;

  /**
   * {@inheritdoc}
   */
  public function buildCard(array &$build) {
    $this->buildCardStyles($build);

    // Process additional card mappings.
    $this->mapFieldsToCardBuild($build, [
      '#media' => 'field_image',
      '#title' => 'name',
      '#content' => 'description',
    ]);

    // Get the URL of the taxonomy term.
    $url = Url::fromRoute('entity.taxonomy_term.canonical', ['taxonomy_term' => $this->id()]);

    // Set the URL of the build array.
    $build['#url'] = $url;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultCardStyles(): array {
    return [
      'card_media_position' => 'card--stacked',
      'media_size' => 'media--large',
      'styles' => '',
    ];
  }

  /**
   * Get view modes that should be rendered as a card.
   *
   * @return string[]
   *   The list of view modes.
   */
  protected function getCardViewModes(): array {
    return ['teaser'];
  }

}
