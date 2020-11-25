<?php

namespace Drupal\uiowa_entities\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'AcademicUnits' widget.
 *
 * @FieldWidget(
 *   id = "uiowa_academic_units_widget",
 *   label = @Translation("Academic Units Config Entity Reference Field Widget"),
 *   description = @Translation("Widget for handling configuration entity references for academic units."),
 *   field_types = {
 *     "uiowa_academic_units",
 *   }
 * )
 */
class AcademicUnitsWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $options = array_filter($this->getSetting('types'));
    $units = [];
    foreach ($options as $option) {
      $units += \Drupal::entityTypeManager()
        ->getStorage('uiowa_academic_unit')
        ->loadByProperties(['type' => $option]);
    }

    foreach ($units as $key => $value) {
      $units[$key] = $value->get('label');
    }

    $element['academic_units'] = [
      '#type' => 'select',
      '#title' => $this->t('Academic Units'),
      '#options' => $units,
      '#default_value' => isset($items[$delta]->academic_units) ? $items[$delta]->academic_units : [],
      '#description' => $this->t('Academic units related to this content.'),
      '#multiple' => TRUE,
    ];

    return $element;
  }

  /**
   * (@inheritdoc)
   */
  public static function defaultSettings() {
    return [
      'types' => ['college']
      ] + parent::defaultSettings();
  }

  /**
   * (@inheritdoc)
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element['types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Which types of academic units should be included?'),
      '#options' => [
        'college' => $this->t('Collegiate'),
        'non-collegiate' => $this->t('Non-Collegiate'),
      ],
      '#default_value' => $this->getSetting('types'),
    ];
    return $element;
  }

  /**
   * (@inheritdoc)
   */
  public function settingsSummary() {
    $summary = [];
    $summary['types'] = $this->t('Included types: @types',
      ['@types' => implode(", ", array_filter($this->getSetting('types')))]);
    return $summary;
  }
}
