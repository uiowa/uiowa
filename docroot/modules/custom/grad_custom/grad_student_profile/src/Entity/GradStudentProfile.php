<?php

namespace Drupal\grad_student_profile\Entity;

use Drupal\uiowa_core\Entity\NodeBundleBase;
use Drupal\uiowa_core\Entity\RendersAsCardInterface;

/**
 * Provides an interface for grad.uiowa.edu student profile entries.
 */
class GradStudentProfile extends NodeBundleBase implements RendersAsCardInterface {

  /**
   * {@inheritdoc}
   */
  public function buildCard(array &$build) {
    parent::buildCard($build);

    // Process additional card mappings.
    $this->mapFieldsToCardBuild($build, [
      '#meta' => 'field_person_distinction',
      '#content' => 'field_person_bio_headline',
    ]);

    $build['#link_indicator'] = TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultCardStyles(): array {
    $dependent_styles = [];
    if (!is_null($this->_referringItem)) {
      $even = $this->getDelta() % 2 !== 0;
      $dependent_styles = [
        'card_media_position' => $even ? 'card--layout-right' : 'card--layout-left',
        'bg' => $even ? 'bg--white' : 'bg--gray',
      ];
    }
    return [
      ...parent::getDefaultCardStyles(),
      'card_media_position' => 'card--layout-left',
      ...$dependent_styles,
      'border' => 'borderless',
      'headline_class' => 'headline--serif headline--uppercase h1',
      'media_size' => 'media--large',
    ];
  }

  /**
   * Get view modes that should be rendered as a card.
   *
   * @return string[]
   *   The list of view modes.
   */
  protected function getCardViewModes(): array {
    return ['card', 'teaser'];
  }

  /**
   * Get the delta of this item from the parent ER list.
   */
  public function getDelta(): int {
    if (!is_null($referring_item = $this->_referringItem)) {
      /** @var \Drupal\Core\Field\EntityReferenceFieldItemList $referring_field */
      $referring_field = $referring_item->getParent();
      if ($referring_field) {
        $parent_entity = $referring_field->getParent();
        $parent_entity = $parent_entity->getEntity();
        if ($parent_entity->hasField('field_content_list_items')) {
          /** @var \Drupal\Core\Field\EntityReferenceFieldItemList $er_list */
          $er_list = $parent_entity->field_content_list_items;
          foreach ($er_list->referencedEntities() as $delta => $entity) {
            if ($this->id() === $entity->id()) {
              return (int) $delta;
            }
          }
        }
      }
    }
    return 0;
  }

}
