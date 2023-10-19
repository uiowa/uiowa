<?php

namespace Drupal\sitenow_media_wysiwyg\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\link\Plugin\Field\FieldType\LinkItem;

/**
 * Plugin implementation of the 'static_map_url' field type.
 *
 * @FieldType(
 *   id = "static_map_url",
 *   label = @Translation("Static Map URL"),
 *   description = @Translation("This field is used to capture the URL of a static map."),
 *   category = @Translation("General"),
 *   default_widget = "static_map_url_widget",
 *   default_formatter = "static_map_url_formatter"
 * )
 */
class StaticMapUrl extends LinkItem {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = parent::propertyDefinitions($field_definition);

    $properties['zoom'] = DataDefinition::create('integer')
      ->setLabel(t('Zoom'));

    $properties['alt'] = DataDefinition::create('string')
      ->setLabel(t('Map alt text'));

    unset($properties['title']);
    unset($properties['options']);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = parent::schema($field_definition);

    unset($schema['columns']['title']);
    unset($schema['columns']['options']);

    $schema['columns']['zoom'] = [
      'description' => 'The zoom level for the static map.',
      'type' => 'int',
      'size' => 'normal',
    ];

    $schema['columns']['alt'] = [
      'description' => 'The alternative text for the static map.',
      'type' => 'varchar',
      'length' => 255,
    ];

    return $schema;
  }

  /**
   * Returns allowed values for 'zoom' sub-field.
   *
   * @return array
   *   The list of allowed values.
   */
  public static function allowedZoomValues(): array {
    return [
      15 => 15,
      16 => 16,
      17 => 17,
      18 => 18,
      19 => 19,
      20 => 20,
    ];
  }

}
