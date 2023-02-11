<?php

namespace Drupal\sitenow_people\Entity;

use Drupal\uiowa_core\Entity\NodeBundleBase;
use Drupal\uiowa_core\Entity\TeaserCardInterface;

/**
 * Provides an interface for person entries.
 */
class Person extends NodeBundleBase implements TeaserCardInterface {

  /**
   * If entity has link directly to source field.
   *
   * @var string|null
   *   field name or null.
   */
  protected $source_link_direct = 'field_person_website_link_direct';

  /**
   * If entity has source link field.
   *
   * @var string|null
   *   field name or null.
   */
  protected $source_link = 'field_person_website';

  /**
   * {@inheritdoc}
   */
  public function buildCard(array &$build) {
    parent::buildCard($build);

    // Process additional card mappings.
    $this->mapFieldsToCardBuild($build, [
      '#subtitle' => 'field_person_position',
      '#meta' => ['field_person_email', 'field_person_phone'],
    ]);

    // Handle link directly to source functionality.
    $build['#url'] = $this->getNodeUrl();

  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultStyles(): array {
    // If ListBlock, otherwise provide node and person teaser defaults.
    // @todo Establish a better identifier for block controlled classes.
    if ($this->view?->id() === 'people_list_block') {
      return [];
    }
    else {
      $default_classes = [
        ...parent::getDefaultStyles(),
        'card_media_position' => 'card--layout-left',
        'media_border' => 'media--border',
        'media_format' => 'media--circle',
        'media_size' => 'media--small',
      ];

      if ($this->view?->id() === 'people') {
        $default_classes['card_list'] = 'card--list';
      }

      return $default_classes;
    }
  }

}
