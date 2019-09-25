<?php

namespace Drupal\sitenow_core\Validate;

use Drupal\Core\Form\FormStateInterface;

/**
 * Request a site webform URL field callable.
 */
class RequestASiteUriConstraint {

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
  public static function validate(array &$element, FormStateInterface $formState, array &$form) {
    $webformKey = $element['#webform_key'];
    $value = $formState->getValue($webformKey);

    $url = parse_url($value);

    if ($formState->getValue('request_type') == 'New') {
      if ($url['scheme'] == 'http') {
        $formState->setError(
          $element,
          t('URL @value must begin with https:// scheme.', [
            '@value' => $value,
          ])
        );
      }
    }

    foreach (['port', 'user', 'pass', 'path', 'query', 'fragment'] as $invalid) {
      if (isset($url[$invalid])) {
        $formState->setError(
          $element,
          t('URL @value must not contain a @invalid.', [
            '@value' => $value,
            '@invalid' => $invalid,
          ])
        );
      }
    }

    // Validate the URL pattern if this is a new site.
    if ($formState->getValue('request_type') == 'New') {
      $pattern = $formState->getValue('url_pattern');
      $pattern = explode('*.', $pattern)[1];

      // The host should not contain more than 4 parts, i.e. subdomains of
      // approved URL patterns. E.g. foo.bar.sites.uiowa.edu is invalid.
      $parts = explode('.', $url['host']);

      // The host should match the pattern minus the '*.' placeholder.
      $match = str_replace('*.', '', $url['host']);

      if (count($parts) > 4 || $url['host'] != $match) {
        $formState->setError(
          $element,
          t('URL @value must match the URL pattern @pattern.', [
            '@value' => $value,
            '@pattern' => $pattern,
          ])
        );
      }
    }
  }

}
