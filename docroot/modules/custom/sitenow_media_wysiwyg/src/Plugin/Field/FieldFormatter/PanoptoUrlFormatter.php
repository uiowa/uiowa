<?php

namespace Drupal\sitenow_media_wysiwyg\Plugin\Field\FieldFormatter;

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
      $elements[$delta] = [
        '#type' => 'markup',
        '#markup' => '<div>' . $values[$delta]['uri'] . '</div>',
        '#allowed_tags' => ['div'],
      ];
    }

    return $elements;
  }

}
