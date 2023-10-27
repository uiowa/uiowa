<?php

namespace Drupal\uiowa_core\Entity;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Provides an interface for paragraph timeline items.
 */
class TimelineItem extends Paragraph implements RendersAsCardInterface {

  use RendersAsCardTrait;
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function buildCard(array &$build) {
    $this->buildCardStyles($build);

    // Process additional card mappings.
    $this->mapFieldsToCardBuild($build, [
      '#title' => 'field_timeline_heading',
      '#content' => 'field_timeline_body',
      '#subtitle' => 'field_timeline_date',
      '#media' => 'field_timeline_media',
    ]);

    $build['#title'] = $this->get('field_timeline_heading')
      ?->get(0)
      ?->getString();

    if ($icon = $this->get('field_timeline_icon')?->view([
      'label' => 'hidden',
    ])) {
      $build['#meta'] = $icon;
      $build['#meta']['#prefix'] = '<div class="timeline__icon-wrapper"><div class="timeline__icon">';
      $build['#meta']['#suffix'] = '</div></div>';
      unset($build['field_timeline_icon']);
    }

    // Check if the timeline card should be linked.
    $field_timeline_link = $this->get('field_timeline_link');

    if ($field_timeline_link && !$field_timeline_link->isEmpty()) {
      $url = $field_timeline_link->get(0)->getUrl();
      $build['#url'] = $url ? $url->toString() : '';
      $build['#link_indicator'] = TRUE;

      if (!empty($field_timeline_link->title)) {
        $build['#link_text'] = $field_timeline_link->title;
      }
    }

    // If we don't have a link set,
    // then we don't want the card linked at all.
    else {
      $build['#url'] = '';
    }

    // Each card is part of a timeline list, so add
    // our timeline wrapper list item.
    $build['#prefix'] = '<li class="timeline--wrapper">';
    $build['#suffix'] = '</li>';

  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultCardStyles(): array {
    return [
      'media_size' => 'media--large',
      'timeline--card' => 'timeline--card',
      'js-scroll' => 'js-scroll',
      'bg--white' => 'bg--white',
    ];
  }

  /**
   * Get view modes that should be rendered as a card.
   *
   * @return string[]
   *   The list of view modes.
   */
  protected function getCardViewModes(): array {
    return ['default'];
  }

}
