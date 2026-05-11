<?php

namespace Drupal\uiowa_heading_advanced\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\uiowa_core\HeadlineHelper;

/**
 * Plugin implementation of the 'heading_advanced_formatter' formatter.
 *
 * This formatter combines a drupal/heading field with heading_config field
 * to render a fully styled heading with all advanced features.
 *
 * @FieldFormatter(
 *   id = "heading_advanced_formatter",
 *   label = @Translation("Heading with Configuration"),
 *   description = @Translation("Renders heading field with styling from heading_config field."),
 *   field_types = {
 *     "heading"
 *   }
 * )
 */
class HeadingAdvancedFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'config_field' => 'field_heading_config',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

    $element['config_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Configuration field name'),
      '#description' => $this->t('The machine name of the heading_config field that provides styling options.'),
      '#default_value' => $this->getSetting('config_field'),
      '#required' => TRUE,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $summary[] = $this->t('Config field: @field', [
      '@field' => $this->getSetting('config_field'),
    ]);
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];
    $entity = $items->getEntity();
    $config_field = $this->getSetting('config_field');

    // Get styling configuration from the config field.
    $styles = HeadlineHelper::getStyles();
    $alignments = HeadlineHelper::getHeadingAlignment();

    foreach ($items as $delta => $item) {
      $config = NULL;

      // Try to load config from the config field.
      if ($entity->hasField($config_field) && !$entity->get($config_field)->isEmpty()) {
        $config = $entity->get($config_field)->first();
      }

      // Build classes based on config.
      $hidden = '';
      $item_style = 'headline block__headline';
      $item_alignment = 'headline--left';

      if ($config) {
        $hidden = $config->hide_headline ? ' sr-only' : '';
        $style_key = $config->headline_style ?? 'default';
        $item_style = isset($styles[$style_key]) ? $styles[$style_key] . $hidden : $item_style . $hidden;
        $alignment_key = $config->headline_alignment ?? 'default';
        $item_alignment = $alignments[$alignment_key] ?? 'headline--left';
      }

      $element[$delta] = [
        '#theme' => 'uiowa_heading_advanced',
        '#text' => strip_tags($item->text),
        '#size' => $item->size,
        '#styles' => $item_style,
        '#alignment' => $item_alignment,
      ];
    }

    return $element;
  }

}
