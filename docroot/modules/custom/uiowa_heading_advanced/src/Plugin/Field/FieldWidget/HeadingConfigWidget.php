<?php

namespace Drupal\uiowa_heading_advanced\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\uiowa_core\HeadlineHelper;

/**
 * Plugin implementation of the 'heading_config_widget' widget.
 *
 * @FieldWidget(
 *   id = "heading_config_widget",
 *   label = @Translation("Heading Configuration"),
 *   field_types = {
 *     "heading_config"
 *   }
 * )
 */
class HeadingConfigWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $heading_size_options = HeadlineHelper::getHeadingOptions();
    // Add h6 for child heading sizes.
    $heading_size_options['h6'] = 'Heading 6';

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

    $element['child_heading_size'] = [
      '#type' => 'select',
      '#title' => $this->t('Child content heading size'),
      '#options' => $heading_size_options,
      '#description' => $this->t('The heading size for all child content elements.'),
      '#default_value' => $items[$delta]->child_heading_size ?? 'h2',
    ];

    return $element;
  }

}
