<?php

namespace Drupal\sitenow_media_wysiwyg\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\sitenow_media_wysiwyg\Plugin\media\Source\StaticMap;
use Drupal\Core\Field\FormatterBase;

/**
 * Static map URL field formatter.
 *
 * @FieldFormatter(
 *   id = "static_map_url_formatter",
 *   label = @Translation("Static Map"),
 *    *   description = @Translation("Display the static map."),
 *   field_types = {
 *    "static_map_url"
 *   }
 * )
 */
class StaticMapUrlFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    /** @var \Drupal\media\MediaInterface $media */
    $media = $items->getEntity();

    if (($source = $media->getSource()) && $source instanceof StaticMap) {
      foreach ($items as $delta => $item) {
        $element[$delta] = [
          '#markup' => StaticMap::create($source->getMetadata($media, 'label')),
        ];
      }
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return $field_definition->getTargetEntityTypeId() === 'media';
  }

}
