<?php

namespace Drupal\uiowa_entities\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\TypedData\DataReferenceTargetDefinition;
use Drupal\Core\Entity\TypedData\EntityDataDefinition;
use Drupal\Core\TypedData\DataReferenceDefinition;

/**
 * Provides a field type of AcademicUnits.
 *
 * @FieldType(
 *   id = "uiowa_academic_units",
 *   label = @Translation("Academic Units"),
 *   description = @Translation("Reference academic unit configuration entities."),
 *   category = @Translation("Reference"),
 *   default_formatter = "uiowa_academic_units_formatter",
 *   default_widget = "uiowa_academic_units_widget",
 * )
 */
class AcademicUnits extends EntityReferenceItem {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $columns = [
      'target_id' => [
        'description' => 'The ID of the target configuration entity.',
        'type' => 'varchar_ascii',
        'length' => 255,
      ],
    ];

    return [
      'columns' => $columns,
      'indexes' => [
        'target_id' => ['target_id'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = [];

    $target_id_definition = DataReferenceTargetDefinition::create('string')
      ->setLabel(t('uiowa_academic_unit ID'))
      ->setRequired(TRUE);

    $properties['target_id'] = $target_id_definition;
    $properties['entity'] = DataReferenceDefinition::create('entity')
      ->setLabel('uiowa_academic_unit')
      ->setDescription(t('The referenced entity'))
      ->setComputed(TRUE)
      ->setReadOnly(FALSE)
      ->setTargetDefinition(EntityDataDefinition::create('uiowa_academic_unit'))
      ->addConstraint('EntityType', 'uiowa_academic_unit');;

    return $properties;
  }

}
