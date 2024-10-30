<?php

namespace Drupal\uiowa_core;

use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\InputBag;

/**
 * Form helper functions
 *
 * This trait contains functions that assist in
 * the construction of custom forms.
 */
trait FormHelpersTrait {

  /**
   * Gets form value from user interaction and URL params.
   */
  public function getFormValue(
    string $param_index,
    array $param_allowed,
    FormStateInterface $form_state,
    InputBag $params,
    String $baseState = '',
  ): String {

    // If the user has already entered a value, use that.
    $param = $baseState;
    if ($form_state->getValue($param_index)) {
      $param = $form_state->getValue($param_index);
    }

    // Else if the given audience param matches our available options,
    // check if we have the current parameter index in the URL query params.
    elseif (array_key_exists($params->get($param_index), $param_allowed) && $params->has($param_index)) {

      // And if we do, set it as our parameter to be used in the form.
      $param = $params->get($param_index);
    }

    return $param;
  }

}
