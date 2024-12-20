<?php

namespace Drupal\uiowa_core\Entity;

use Drupal\Component\Utility\UrlHelper;
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

    // Process timeline link field for both regular and media links.
    if (!empty($build['field_timeline_link'][0])) {
      $url = $build['field_timeline_link'][0]['#url'] ?? NULL;
      $title = $build['field_timeline_link'][0]['#title'] ?? NULL;

      if ($url) {
        $url_string = $url->toString();
        $build['#url'] = $url_string;
        $build['#link_indicator'] = TRUE;

        if ($title) {
          if (UrlHelper::isExternal($url_string)) {
            $build['#link_text'] = str_starts_with($title, 'http') ? NULL : $title;
          }
          else {
            $internal_path = str_starts_with($url_string, '/') ? $url_string : '/' . $url_string;
            $alias = \Drupal::service('path_alias.manager')->getAliasByPath($internal_path);

            $build['#url'] = $alias ?: $url_string;
            $build['#link_text'] = str_starts_with($title, '/') ? NULL : $title;
          }
        }
      }
      else {
        $build['#url'] = '';
        $build['#link_indicator'] = FALSE;
      }

      unset($build['field_timeline_link']);
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
