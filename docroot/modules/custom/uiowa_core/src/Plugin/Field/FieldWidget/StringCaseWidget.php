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

    $element_value = $element['value'];
    unset($element['value']);
    $wrapper_id = Html::getUniqueId('unique-id-wrapper');
    $element['string_case_wrapper'] = [
      '#type' => 'container',
      '#weight' => 0,
      '#attributes' => [
        'id' => $wrapper_id,
      ],
    ];

    $element['string_case_wrapper']['value'] = [
      '#ajax' => [
        'callback' => [
          get_called_class(),
          'caseConverter',
        ],
        'wrapper' => $wrapper_id,
        'disable-refocus' => TRUE,
      ],
    ] + $element_value;

    $element['string_case_wrapper']['#element_validate'][] = [
      get_called_class(),
      'caseValidate',
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function caseValidate(&$element, FormStateInterface $form_state, $form) {
    if (isset($element['value']['#value'])) {
      $converted_value = Html::cleanCssIdentifier($element['value']['#value']);
      $element['value']['#value'] = $converted_value;
      $form_state->set($element['value'], $converted_value);
    }
  }

  public static function caseConverter(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();

    // Check if the trigger element is a nested field.
    if (isset($triggering_element['#array_parents'])) {
      static::caseValidate($triggering_element, $form_state, $form);
      // Access the part of the form we want to return.
      return NestedArray::getValue($form,
        array_slice($triggering_element['#array_parents'], 0, -4));
    }
    return $form;

  }

}
