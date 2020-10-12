<?php

namespace Drupal\layout_builder_custom\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Provides a field type of UiowaHeadline.
 *
 * @FieldType(
 *   id = "uiowa_headline",
 *   label = @Translation("Headline"),
 *   description = @Translation("Parent headline for collections of content."),
 *   default_formatter = "uiowa_headline_formatter",
 *   default_widget = "uiowa_headline_widget",
 * )
 */
class UiowaHeadline extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'headline' => [
          'type' => 'varchar',
          'length' => 255,
          'not null' => FALSE,
        ],
        'heading_size' => [
          'type' => 'varchar',
          'length' => 255,
          'not null' => TRUE,
        ],
        'hide_headline' => [
          'type' => 'int',
          'not null' => TRUE,
          'default' => 0,
        ],
        'headline_style' => [
          'type' => 'varchar',
          'length' => 255,
          'not null' => FALSE,
        ],
        'child_heading_size' => [
          'type' => 'varchar',
          'length' => 255,
          'not null' => TRUE,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $headline = $this->get('headline')->getValue();
    $child_heading_size = $this->get('child_heading_size')->getValue();
    return empty($headline) && empty($child_heading_size);
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = [];

    $properties['headline'] = DataDefinition::create('string')
      ->setLabel(t('Headline'))
      ->setDescription(t('Parent headline over collections of content.'));

    $properties['heading_size'] = DataDefinition::create('string')
      ->setLabel(t('Headline size'))
      ->setDescription(t('Heading size for the parent headline.'));

    $properties['hide_headline'] = DataDefinition::create('string')
      ->setLabel(t('Hide headline'))
      ->setDescription(t('Visually hide block headline.'));

    $properties['headline_style'] = DataDefinition::create('string')
      ->setLabel(t('Headline style'))
      ->setDescription(t('Set the headline style.'));

    $properties['child_heading_size'] = DataDefinition::create('string')
      ->setLabel(t('Child content headline size'))
      ->setDescription(t('Heading size for any child content.'));

    return $properties;
  }

}
