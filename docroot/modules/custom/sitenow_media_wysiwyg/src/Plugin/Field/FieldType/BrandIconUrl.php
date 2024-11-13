<?php

namespace Drupal\sitenow_media_wysiwyg\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\link\Plugin\Field\FieldType\LinkItem;

/**
 * Plugin implementation of the 'brand_icon_url' field type.
 *
 * @FieldType(
 *   id = "brand_icon_url",
 *   label = @Translation("Brand Icon URL"),
 *   description = @Translation("This field is used to capture the URL of the brand icon svg"),
 *   category = @Translation("General"),
 *   default_widget = "brand_icon_url_widget",
 *   default_formatter = "brand_icon_url_formatter"
 * )
 */
class BrandIconUrl extends LinkItem {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = parent::propertyDefinitions($field_definition);

    $properties['alt'] = DataDefinition::create('string')
      ->setLabel(t('Brand icon alt text'));

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

    $schema['columns']['alt'] = [
      'description' => 'The alternative text for the brand icon.',
      'type' => 'varchar',
      'length' => 255,
    ];

    return $schema;
  }

}
