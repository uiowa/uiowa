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
   * @inheritDoc
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);
    $values = $items->getValue();

    foreach ($elements as $delta => $entity) {
      $parsed_url = UrlHelper::parse($values[$delta]['uri']);
      $elements[$delta] = [
        '#type' => 'markup',
        '#markup' => '
          <div
            data-link="' . $parsed_url['query']['id'] . '"
            data-width="700"
            data-height="422"
            id="panopto_player-1"
          ></div>
        ',
        '#allowed_tags' => ['div'],
        '#attached' => ['library' => ['sitenow_media_wysiwyg/panopto']],
      ];
    }

    return $elements;
  }

}
