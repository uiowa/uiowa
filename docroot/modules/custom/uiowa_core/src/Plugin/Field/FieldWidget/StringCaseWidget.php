<?php

namespace Drupal\uiowa_core\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\StringTextfieldWidget;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'UiowaHeadlineDefaultWidget' widget.
 *
 * @FieldWidget(
 *   id = "string_case_widget",
 *   label = @Translation("Textfield (converts to dash-case)"),
 *   description = @Translation("A textfield that will be converted to dash-case."),
 *   field_types = {
 *     "string",
 *   }
 * )
 */
class StringCaseWidget extends StringTextfieldWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {

    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    // Add an AJAX callback to the form element.
    $wrapper_id = Html::getUniqueId('unique-id-wrapper');
    $element['value'] = $element['value'] + [
      '#prefix' => '<div id="' . $wrapper_id . '">',
      '#suffix' => '</div>',
      '#ajax' => [
        'callback' => [
          get_called_class(),
          'ajaxConvert',
        ],
        'wrapper' => $wrapper_id,
        'disable-refocus' => TRUE,
      ],
      '#attributes' => [
        'id' => $wrapper_id,
      ],
    ];

    return $element;
  }

  /**
   * AJAX handler for in-place string conversion.
   */
  public static function ajaxConvert(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();

    $triggering_element['#value'] = Html::cleanCssIdentifier($triggering_element['#value']);
    return $triggering_element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(
    array $values,
    array $form,
    FormStateInterface $form_state
  ) {
    $values = parent::massageFormValues($values, $form, $form_state);
    foreach ($values as &$value) {
      $value['value'] = Html::cleanCssIdentifier($value['value']);
    }
    return $values;
  }

}
