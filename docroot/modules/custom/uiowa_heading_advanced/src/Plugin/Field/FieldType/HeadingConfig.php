<?php

namespace Drupal\uiowa_heading_advanced\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Provides a field type for heading configuration.
 *
 * @FieldType(
 *   id = "heading_config",
 *   label = @Translation("Heading Configuration"),
 *   description = @Translation("Advanced heading configuration for styling, alignment, and child heading control."),
 *   default_widget = "heading_config_widget",
 *   default_formatter = "string",
 * )
 */
class HeadingConfig extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'hide_headline' => [
          'type' => 'int',
          'size' => 'tiny',
          'not null' => TRUE,
          'default' => 0,
        ],
        'headline_style' => [
          'type' => 'varchar',
          'length' => 255,
          'not null' => TRUE,
          'default' => 'default',
        ],
        'headline_alignment' => [
          'type' => 'varchar',
          'length' => 255,
          'not null' => TRUE,
          'default' => 'default',
        ],
        'child_heading_size' => [
          'type' => 'varchar',
          'length' => 2,
          'not null' => TRUE,
          'default' => 'h2',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    // This field is never truly empty since it has defaults.
    // Only consider it empty if all values are at defaults.
    $hide_headline = $this->get('hide_headline')->getValue();
    $headline_style = $this->get('headline_style')->getValue();
    $headline_alignment = $this->get('headline_alignment')->getValue();
    $child_heading_size = $this->get('child_heading_size')->getValue();

    return empty($hide_headline)
      && $headline_style === 'default'
      && $headline_alignment === 'default'
      && $child_heading_size === 'h2';
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = [];

    $properties['hide_headline'] = DataDefinition::create('integer')
      ->setLabel(t('Hide headline'))
      ->setDescription(t('Visually hide block headline (accessible to screen readers).'));

    $properties['headline_style'] = DataDefinition::create('string')
      ->setLabel(t('Headline style'))
      ->setDescription(t('Visual style for the headline.'));

    $properties['headline_alignment'] = DataDefinition::create('string')
      ->setLabel(t('Headline alignment'))
      ->setDescription(t('Text alignment for the headline.'));

    $properties['child_heading_size'] = DataDefinition::create('string')
      ->setLabel(t('Child heading size'))
      ->setDescription(t('Heading size for child content elements.'));

    return $properties;
  }

}
