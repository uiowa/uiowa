<?php

namespace Drupal\admissions_core\Entity;

use Drupal\Core\Url;
use Drupal\taxonomy\Entity\Term;
use Drupal\uiowa_core\Entity\RendersAsCardInterface;
use Drupal\uiowa_core\Entity\RendersAsCardTrait;

/**
 * Provides a bundle for academic group taxonomy terms.
 */
class AcademicGroups extends Term implements RendersAsCardInterface {

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
    $default_classes = [
      'card_media_position' => '',
      'media_size' => 'media--large',
      'styles' => '',
    ];

    return $default_classes;
  }

}
