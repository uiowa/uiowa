<?php

namespace Drupal\layout_builder_custom\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'UiowaHeadlineDefaultWidget' widget.
 *
 * @FieldWidget(
 *   id = "uiowa_headline_widget",
 *   label = @Translation("Uiowa Headline Field Type Default Widget"),
 *   description = @Translation("Uiowa Headline Field Type Default Widget"),
 *   field_types = {
 *     "uiowa_headline",
 *   }
 * )
 */
class UiowaHeadlineWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $heading_size_options = [
      'h2' => 'Heading 2',
      'h3' => 'Heading 3',
      'h4' => 'Heading 4',
      'h5' => 'Heading 5',
    ];

    $element['container'] = [
      '#type' => 'container',
      '#title' => 'Headline',
      '#attributes' => [
        'class' => 'uiowa-headline--container',
      ],
    ];

    $element['container']['headline'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Headline'),
      '#size' => 80,
      '#default_value' => isset($items[$delta]->headline) ? $items[$delta]->headline : NULL,
      '#attributes' => [
        'id' => 'uiowa-headline-field',
      ],
    ];

    $element['container']['hide_headline'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Visually hide title'),
      '#default_value' => isset($items[$delta]->hide_headline) ? $items[$delta]->hide_headline : 0,
      '#attributes' => [
        'id' => 'uiowa-headline-hide-headline-field',
      ],
      '#states' => [
        'visible' => [
          ':input[id="uiowa-headline-field"]' => [
            'filled' => TRUE,
          ],
        ],
      ],
    ];

    $element['container']['heading_size'] = [
      '#type' => 'select',
      '#title' => $this->t('Headline size'),
      '#options' => $heading_size_options,
      '#description' => $this->t('The heading size for the block title. Children headings will be set one level lower.'),
      '#default_value' => isset($items[$delta]->heading_size) ? $items[$delta]->heading_size : 'h2',
      '#states' => [
        'visible' => [
          ':input[id="uiowa-headline-field"]' => [
            'filled' => TRUE,
          ],
        ],
      ],
    ];

    $element['container']['headline_style'] = [
      '#type' => 'select',
      '#title' => $this->t('Headline style'),
      '#options' => [
        'default' => $this->t('Default'),
        'headline_bold_serif' => $this->t('Headline bold serif'),
        'headline_bold_serif_highlight' => $this->t('Headline bold serif highlight'),
      ],
      '#default_value' => isset($items[$delta]->headline_style) ? $items[$delta]->headline_style : 'default',
      '#states' => [
        'visible' => [
          ':input[id="uiowa-headline-field"]' => [
            'filled' => TRUE,
          ],
        ],
      ],
    ];

    // Add an additional option for children headings.
    $heading_size_options['h6'] = 'Heading 6';

    $element['container']['child_heading_size'] = [
      '#type' => 'select',
      '#title' => $this->t('Child content heading size'),
      '#options' => $heading_size_options,
      '#default_value' => isset($items[$delta]->child_heading_size) ? $items[$delta]->child_heading_size : 'h2',
      '#description' => $this->t('The heading size for all children headings.'),
      '#states' => [
        'visible' => [
          ':input[id="uiowa-headline-field"]' => [
            'filled' => FALSE,
          ],
        ],
      ],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    foreach ($values as $delta => $data) {
      $values[$delta]['headline'] = $data['container']['headline'];
      $values[$delta]['hide_headline'] = $data['container']['hide_headline'];
      $values[$delta]['headline_style'] = $data['container']['headline_style'];
      $values[$delta]['heading_size'] = $data['container']['heading_size'];
      $values[$delta]['child_heading_size'] = $data['container']['child_heading_size'];
      unset($values[$delta]['container']);
    }
    return $values;
  }

}
