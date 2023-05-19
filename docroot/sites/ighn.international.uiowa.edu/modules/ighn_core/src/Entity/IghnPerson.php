<?php

namespace Drupal\ighn_core\Entity;

use Drupal\uiowa_core\Entity\NodeBundleBase;
use Drupal\uiowa_core\Entity\RendersAsCardInterface;

/**
 * Provides an interface for IGHN person entries.
 */
class IghnPerson extends NodeBundleBase implements RendersAsCardInterface {

  /**
   * {@inheritdoc}
   */
  public function buildCard(array &$build) {
    parent::buildCard($build);

    // Add the person library.
    $build['#attached']['library'][] = 'uids_base/person';

    // Process additional card mappings.
    $this->mapFieldsToCardBuild($build, [
      '#media' => 'field_ighn_person_image',
      '#subtitle' => 'field_ighn_person_credentials',
      '#title_heading_size' => 'h3',
      '#content' => [
        'field_ighn_person_position_title',
        'field_ighn_primary_college',
        'field_ighn_person_department',
        'field_ighn_person_focus_areas',
        'field_ighn_person_language',
        'field_ighn_person_global_regions',
        'field_ighn_person_biography',
      ],
    ]);

    // IGHN Person does not have an option to link direct
    // to source or not. If a link exists,
    // always add it as a link directly to source.
    $source_link = 'field_ighn_person_cv_link';
    $link = $this->get($source_link)->uri;
    if (isset($link) && !empty($link)) {
      $build['#url'] = $this
        ->get($source_link)
        ?->get(0)
        ?->getUrl()
        ?->toString();
    }
    // If we don't have a link set,
    // then we don't want the card linked at all.
    else {
      $build['#url'] = '';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultCardStyles(): array {
    return [
      ...parent::getDefaultCardStyles(),
      'media_format' => 'media',
      'media_size' => 'media',
      'card_media_position' => 'card--stacked',
      'styles' => 'bg--white',
      'border' => '',
    ];
  }

}
