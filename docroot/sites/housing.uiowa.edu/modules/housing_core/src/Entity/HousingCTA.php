<?php

namespace Drupal\housing_core\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
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
      '#title' => 'field_housing_cta_title',
    ]);

    $build['#url'] = $this->get('field_housing_cta_link')?->get(0)?->getUrl()?->toString();
    if (!empty($this->get('field_housing_cta_link')->title)) {
      $build['#link_text'] = $this->get('field_housing_cta_link')->title;
    }
    $build['#link_indicator'] = TRUE;

  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultCardStyles(): array {
    $admin_context = \Drupal::service('router.admin_context');
    $ctaCardClasses = '';

    if (!$admin_context->isAdminRoute()) {
      $parent = $this->getParentEntity();
      if ($parent instanceof ContentEntityInterface) {
        $field_map = [
          'field_residence_hall_cta' => 'card--centered bg--white',
          'field_residence_hall_contact' => 'card--layout-left borderless',
          'field_residence_hall_bldg_links' => 'card--layout-left borderless',
        ];

        $parent_fields = array_keys($field_map);

        foreach ($parent_fields as $parent_field) {
          if ($parent->hasField($parent_field)) {
            foreach ($parent->get($parent_field)->getValue() as $item) {
              if ($item['target_id'] === $this->id()) {
                $ctaCardClasses = $field_map[$parent_field];
                break 2;
              }
            }
          }
        }
      }
    }

    return [
      'headline_class' => 'headline--serif',
      'styles' => $ctaCardClasses,
      'card_media_position' => 'card--stacked',
      'media_format' => 'media--circle media--border',
      'media_size' => 'media--small',
    ];
  }

}
