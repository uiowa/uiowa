<?php

namespace Drupal\layout_builder_custom\Plugin\Field\FieldType;

/**
 * Provides a field type of uiowaBlockHeadline.
 * 
 * @FieldType(
 *   id = "field_uiowa_block_headline",
 *   label = @Translation("Block Headline"),
 *   default_formatter = "uiowa_block_headline_formatter",
 *   default_widget = "uiowa_block_headline_widget",
 * )
 */
class UiowaBlockHeadline extends FieldItemBase {

/**
 * {@inheritdoc}
 */
public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'headline' => [
          'type' => 'text_long',
          'length' => 255,
          'not null' => FALSE,
        ],
        'heading_size' => [
            'type' => 'list_string',
        ],
        'hide_headline' => [
            'type' => 'boolean',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $item = $this->getValue();
    return $item['headline'] === NULL || $value === '';
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['headline'] = DataDefinition::create('string')
      ->setLabel(t('Block headline'));

    $properties['heading_size'] = DataDefinition::create('string')
      ->setLabel(t('Block headline size'));

    $properties['hide_headline'] = DataDefinition::create('string')
      ->setLabel(t('Visually hide block headline'));
    return $properties;
  }

}
