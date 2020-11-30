<?php

namespace Drupal\uiowa_entities\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

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
class AcademicUnitsWidget extends WidgetBase implements ContainerFactoryPluginInterface {

  /**
   * The EntityTypeManager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * AcademicUnitsWidget constructor.
   *
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, EntityTypeManager $entityTypeManager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($plugin_id, $plugin_definition, $configuration['field_definition'], $configuration['settings'], $configuration['third_party_settings'], $container
      ->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    // Grab available units.
    $units = [];
    $options = array_filter($this->getSetting('types'));
    foreach ($options as $option) {
      $units += $this->entityTypeManager
        ->getStorage('uiowa_academic_unit')
        ->loadByProperties(['type' => $option]);
    }
    // Update values to the text labels
    // rather than the objects themselves.
    array_walk($units, function (&$value, $key) {
      $value = $value->get('label');
    });

    $element['value'] = $element + [
      '#type' => 'select',
      '#options' => $units,
      '#default_value' => isset($items[$delta]->academic_units) ? $items[$delta]->academic_units : [],
      '#multiple' => TRUE,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'types' => ['college'],
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element['types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Which types of academic units should be included?'),
      '#options' => [
        // Options are hardcoded in, but this could be updated
        // to pull available options directly from the config entity.
        'college' => $this->t('Collegiate'),
        'non-collegiate' => $this->t('Non-Collegiate'),
      ],
      '#default_value' => $this->getSetting('types'),
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $summary['types'] = $this->t('Included types: @types',
      ['@types' => implode(", ", array_filter($this->getSetting('types')))]);
    return $summary;
  }

}
