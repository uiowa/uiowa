<?php

namespace Drupal\uiowa_heading_advanced\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'heading_config_simple_widget' widget.
 *
 * This widget excludes child_heading_size for blocks that don't need it.
 *
 * @FieldWidget(
 *   id = "heading_config_simple_widget",
 *   label = @Translation("Heading Configuration (Simple)"),
 *   description = @Translation("Without child heading size option."),
 *   field_types = {
 *     "heading_config"
 *   }
 * )
 */
class HeadingConfigSimpleWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element['hide_headline'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Visually hide headline'),
      '#description' => $this->t('Hides the headline visually but keeps it accessible to screen readers.'),
      '#default_value' => $items[$delta]->hide_headline ?? 0,
    ];

    $element['headline_style'] = [
      '#type' => 'select',
      '#title' => $this->t('Headline style'),
      '#options' => [
        'default' => $this->t('Default'),
        'headline_bold_serif' => $this->t('Bold serif'),
        'headline_bold_serif_underline' => $this->t('Bold serif, underlined'),
      ],
      '#default_value' => $items[$delta]->headline_style ?? 'default',
    ];

    $element['headline_alignment'] = [
      '#type' => 'select',
      '#title' => $this->t('Headline alignment'),
      '#options' => [
        'default' => $this->t('Left (default)'),
        'headline_alignment_center' => $this->t('Center'),
      ],
      '#default_value' => $items[$delta]->headline_alignment ?? 'default',
    ];

    // Set child_heading_size as hidden value. The simple widget
    // should be used for blocks without set children.
    $element['child_heading_size'] = [
      '#type' => 'value',
      '#value' => $items[$delta]->child_heading_size ?? 'h2',
    ];

    return $element;
  }

}
