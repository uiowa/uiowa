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

    $element['block_title'] = [
      '#type' => 'fieldset',
      '#title' => t('Block Title'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
      '#tree' => FALSE,
    ];

    $element['block_title']['headline'] = [
      '#type' => 'textfield',
      '#title' => t('Headline'),
      '#size' => 80,
      '#default_value' => isset($items[$delta]->headline) ? $items[$delta]->headline : NULL,
    ];

    $element['block_title']['heading_size'] = [
      '#type' => 'select',
      '#title' => t('Heading size'),
      '#options' => $heading_size_options,
      '#default_value' => isset($items[$delta]->heading_size) ? $items[$delta]->heading_size : 'h2',
    ];

    $element['block_title']['hide_headline'] = [
      '#type' => 'checkbox',
      '#title' => t('Visually hide title'),
      '#default_value' => isset($items[$delta]->hide_headline) ? $items[$delta]->hide_headline : NULL,
    ];

    return $element;
  }

}
