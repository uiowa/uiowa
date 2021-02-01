<?php

namespace Drupal\uiowa_entities\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsSelectWidget;
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
 *   label = @Translation("Academic Units Field Widget"),
 *   description = @Translation("Widget for handling configuration entity references for academic units."),
 *   field_types = {
 *     "uiowa_academic_units",
 *   },
 *   multiple_values = TRUE
 * )
 */
class AcademicUnitsWidget extends OptionsSelectWidget implements ContainerFactoryPluginInterface {

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
  protected function getOptions(FieldableEntityInterface $entity) {
    if (!isset($this->options)) {
      // Grab available units.
      $options = [];
      $units = array_filter($this->getSetting('types'));
      foreach ($units as $unit) {
        $options += $this->entityTypeManager
          ->getStorage('uiowa_academic_unit')
          ->loadByProperties(['type' => $unit]);
      }
      // Update values to the text labels
      // rather than the objects themselves.
      array_walk($options, function (&$value, $key) {
        $value = $value->get('label');
      });
      // Add an empty option if the widget needs one.
      if ($empty_label = $this->getEmptyLabel()) {
        $options = ['_none' => $empty_label] + $options;
      }
      $this->options = $options;
    }
    return $this->options;
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
      '#title' => $this->t('Unit Types'),
      '#description' => $this->t('Which types of academic units should be included? At least one must be selected.'),
      '#options' => [
        // Options are hardcoded in, but this could be updated
        // to pull available options directly from the config entity.
        'college' => $this->t('Collegiate'),
        'non-collegiate' => $this->t('Non-Collegiate'),
      ],
      '#required' => TRUE,
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
