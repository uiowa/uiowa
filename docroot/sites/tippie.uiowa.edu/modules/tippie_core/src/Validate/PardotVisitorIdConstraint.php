<?php

namespace Drupal\tippie_core\Validate;

use Drupal\Core\Form\FormStateInterface;

/**
 * Look for the visitor ID cookie and save to the hidden form field.
 */
class PardotVisitorIdConstraint {

  /**
   * Validates given element.
   *
   * @param array $element
   *   The form element to process.
   * @param Drupal\Core\Form\FormStateInterface $formState
   *   The form state.
   * @param array $form
   *   The complete form structure.
   */
  public static function validate(array &$element, FormStateInterface $formState, array &$form): void {
    $visitor_id = NULL;

    $cookies = \Drupal::request()->cookies->all();
    foreach ($cookies as $cookie_name => $cookie_value) {
      if (preg_match('/visitor_id\d+$/', $cookie_name)) {
        $visitor_id = $cookie_value;
        break;
      }
    }

    if ($visitor_id) {
      $formState->setValue('visitorid', $visitor_id);
    }
  }

}
