<?php

namespace Drupal\layout_builder_custom\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'UiowaBlockHeadlineDefaultWidget' widget.
 *
 * @FieldWidget(
 *   id = "uiowa_block_headline_widget",
 *   label = @Translation("Uiowa Block Headline Field Type Default Widget"),
 *   description = @Translation("Uiowa Block Headline Field Type Default Widget"),
 *   field_types = {
 *     "uiowa_block_headline",
 *   }
 * )
 */
class UiowaBlockHeadlineWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $heading_size_options = [
      'h2' => 'Heading 2',
      'h3' => 'Heading 3',
      'h4' => 'Heading 4',
      'h5' => 'Heading 5',
      'h6' => 'Heading 6',
    ];

    $element['container'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => 'block-title--container',
      ],
    ];

    $element['container']['headline'] = [
      '#type' => 'textfield',
      '#title' => t('Headline'),
      '#size' => 80,
      '#default_value' => $items[$delta]->headline,
    ];

    $element['container']['hide_headline'] = [
      '#type' => 'checkbox',
      '#title' => t('Visually hide title'),
      '#default_value' => isset($items[$delta]->hide_headline) ? $items[$delta]->hide_headline : 0,
      '#attributes' => [
        'name' => 'block-headline-visually-hide',
      ],
    ];

    $element['container']['heading_size'] = [
      '#type' => 'select',
      '#title' => t('Heading size'),
      '#options' => $heading_size_options,
      '#description' => t('The heading size for the block title, if used, or the size of child content headings.'),
      '#default_value' => isset($items[$delta]->heading_size) ? $items[$delta]->heading_size : 'h2',
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
      $values[$delta]['heading_size'] = $data['container']['heading_size'];
      $values[$delta]['child_heading_size'] = $data['container']['child_heading_size'];
      unset($values[$delta]['container']);
    }

    return $values;
  }

}
