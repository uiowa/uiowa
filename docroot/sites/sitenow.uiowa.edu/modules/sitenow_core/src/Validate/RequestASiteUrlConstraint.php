<?php

namespace Drupal\sitenow_core\Validate;

use Drupal\Core\Form\FormStateInterface;

/**
 * Request a site webform URL field callable.
 */
class RequestASiteUrlConstraint {

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

    // Skip empty unique fields or arrays (aka #multiple).
    if ($value === '' || is_array($value)) {
      return;
    }

    if (parse_url($value, PHP_URL_SCHEME) == 'http') {
      $formState->setError(
        $element,
        t('URL @value must begin with https:// scheme.', [
          '@value' => $value,
        ])
      );
    }

    foreach (['port', 'user', 'pass', 'path', 'query', 'fragment'] as $invalid) {
      if ($url = parse_url($value)) {
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
    }

    // Validate the URL pattern if this is a new site.
    if ($formState->getValue('request_type') == 'New') {
      $pattern = $formState->getValue('url_pattern');
      $pattern = explode('*.', $pattern)[1];

      // This should already be a valid URL via builtin webform validation.
      $host = parse_url($value, PHP_URL_HOST);

      // The host should not contain more than 4 parts, i.e. subdomains of
      // approved URL patterns. E.g. foo.bar.sites.uiowa.edu is invalid.
      $parts = explode('.', $host);

      // The host should match the pattern minus the '*.' placeholder.
      $match = str_replace('*.', '', $host);

      if (count($parts) > 4 || $host != $match) {
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
