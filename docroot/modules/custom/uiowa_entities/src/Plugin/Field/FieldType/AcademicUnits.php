<?php

namespace Drupal\uiowa_entities\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
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
class AcademicUnits extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $target_type_info = \Drupal::entityTypeManager()->getDefinition('uiowa_academic_unit');

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
  public function isEmpty() {
    if ($this->target_id !== NULL) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = [];
    $target_info = \Drupal::entityTypeManager()->getDefinition('uiowa_academic_unit');

    $target_id_definition = DataReferenceTargetDefinition::create('string')
      ->setLabel(t('uiowa_academic_unit ID'));
    $target_id_definition->setRequired(TRUE);
    $properties['target_id'] = $target_id_definition;
    $properties['entity'] = DataReferenceDefinition::create('entity')
      ->setLabel($target_info->getLabel())
      ->setDescription(t('The referenced entity'))
      // The entity object is computed out of the entity ID.
      ->setComputed(TRUE)
      ->setReadOnly(FALSE)
      ->setTargetDefinition(EntityDataDefinition::create('uiowa_academic_unit'));
    return $properties;
  }

}
