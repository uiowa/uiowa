<?php

namespace Drupal\layout_builder_custom\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\uiowa_core\HeadlineHelper;

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
    $element = HeadlineHelper::getElement([
      'headline' => $items[$delta]->headline ?? NULL,
      'hide_headline' => $items[$delta]->hide_headline ?? 0,
      'heading_size' => $items[$delta]->heading_size ?? 'h2',
      'headline_style' => $items[$delta]->headline_style ?? 'default',
      'child_heading_size' => $items[$delta]->child_heading_size ?? 'h2',
      'description' => $items[$delta]->getFieldDefinition()->getDescription() ?? '',
    ]);

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
