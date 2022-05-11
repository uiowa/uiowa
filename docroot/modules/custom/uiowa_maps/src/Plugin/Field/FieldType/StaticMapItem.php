<?php

namespace Drupal\uiowa_maps\Plugin\Field\FieldType;

use Drupal\Component\Utility\Random;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Defines the 'uiowa_maps_static_map' field type.
 *
 * @FieldType(
 *   id = "uiowa_maps_static_map",
 *   label = @Translation("Static Map"),
 *   category = @Translation("UIowa"),
 *   default_widget = "uiowa_maps_static_map",
 *   default_formatter = "uiowa_maps_static_map_default"
 * )
 */
class StaticMapItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    if ($this->link !== NULL) {
      return FALSE;
    }
    elseif ($this->zoom !== NULL) {
      return FALSE;
    }
    elseif ($this->label !== NULL) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {

    $properties['link'] = DataDefinition::create('uri')
      ->setLabel(t('Link'));
    $properties['zoom'] = DataDefinition::create('integer')
      ->setLabel(t('Zoom'));
    $properties['label'] = DataDefinition::create('string')
      ->setLabel(t('Label'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraints() {
    $constraints = parent::getConstraints();

    $options['link']['NotBlank'] = [];

    $options['zoom']['AllowedValues'] = array_keys(StaticMapItem::allowedZoomValues());

    $options['zoom']['NotBlank'] = [];

    $options['label']['NotBlank'] = [];

    $constraint_manager = \Drupal::typedDataManager()->getValidationConstraintManager();
    $constraints[] = $constraint_manager->create('ComplexData', $options);

    return $constraints;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {

    $columns = [
      'link' => [
        'type' => 'varchar',
        'length' => 2048,
      ],
      'zoom' => [
        'type' => 'int',
        'size' => 'normal',
      ],
      'label' => [
        'type' => 'varchar',
        'length' => 255,
      ],
    ];

    $schema = [
      'columns' => $columns,
      // @DCG Add indexes here if necessary.
    ];

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {

    $random = new Random();

    $tlds = ['com', 'net', 'gov', 'org', 'edu', 'biz', 'info'];
    $domain_length = mt_rand(7, 15);
    $protocol = mt_rand(0, 1) ? 'https' : 'http';
    $www = mt_rand(0, 1) ? 'www' : '';
    $domain = $random->word($domain_length);
    $tld = $tlds[mt_rand(0, (count($tlds) - 1))];
    $values['link'] = "$protocol://$www.$domain.$tld";

    $values['zoom'] = array_rand(self::allowedZoomValues());

    $values['label'] = $random->word(mt_rand(1, 255));

    return $values;
  }

  /**
   * Returns allowed values for 'zoom' sub-field.
   *
   * @return array
   *   The list of allowed values.
   */
  public static function allowedZoomValues() {
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
