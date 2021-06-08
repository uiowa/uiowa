<?php

namespace Drupal\sitenow_media_wysiwyg\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\link\Plugin\Field\FieldFormatter\LinkFormatter;

/**
 * Panopto URL field formatter.
 *
 * @FieldFormatter(
 *   id = "panopto_url_formatter",
 *   label = @Translation("Panopto"),
 *   description = @Translation("Display the panopto instance."),
 *   field_types = {
 *     "panopto_url"
 *   }
 * )
 */
class PanoptoUrlFormatter extends LinkFormatter {

  /**
   * Short description message...
   *
   * @inheritDoc
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);
    $values = $items->getValue();
    $parent = $items->getParent()->getEntity();
    $parent_id = $parent->id();

    foreach ($elements as $delta => $entity) {
      $parsed_url = UrlHelper::parse($values[$delta]['uri']);
      // @todo replace 'panopto_player-1' id with $unique_id.
      $unique_id = 'media-' . $parent_id . '-'. $delta;
      $elements[$delta] = [
        '#type' => 'markup',
        '#markup' => '
          <div
            data-link="' . $parsed_url['query']['id'] . '"
            data-width="720"
            data-height="405"
            id="panopto_player-1"
            class="panopto-player"
          ></div>
        ',
        '#allowed_tags' => ['div'],
        '#attached' => ['library' => ['sitenow_media_wysiwyg/panopto']],
      ];
    }

    return $elements;
  }

}
