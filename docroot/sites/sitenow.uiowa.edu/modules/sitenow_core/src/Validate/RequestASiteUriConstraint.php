<?php

namespace Drupal\sitenow_core\Validate;

use Drupal\Core\Form\FormStateInterface;
use Uiowa\Multisite;

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
    $value = rtrim($formState->getValue($webformKey), '/');

    $url = parse_url($value);

    // Set Error if URL contains www.
    if (stristr($url['host'], 'www.')) {
      return $formState->setError(
        $element,
        t('URL must not contain www.', [
          '@value' => $value,
        ])
      );
    }

    // Set Error if URL contains an uppercase letter.
    if (preg_match('/[A-Z]/', $url['host'])) {
      return $formState->setError(
        $element,
        t('URL @value must be lowercase.', [
          '@value' => $value,
        ])
      );
    }

    foreach (['port', 'user', 'pass', 'path', 'query', 'fragment'] as $invalid) {
      if (isset($url[$invalid])) {
        $extra = '';
        if ($invalid == 'path' && $formState->getValue('request_type') == 'Existing') {
          $extra = 'URL must not contain a path.';
        }
        return $formState->setError(
          $element,
          t('URL @value must not contain a @invalid. @extra', [
            '@value' => $value,
            '@invalid' => $invalid,
            '@extra' => $extra,
          ])
        );
      }
    }

    // Validate the URL pattern if this is a new site.
    if ($formState->getValue('request_type') == 'New') {
      if ($url['scheme'] == 'http') {
        return $formState->setError(
          $element,
          t('URL @value must begin with https:// scheme.', [
            '@value' => $value,
          ])
        );
      }

      $pattern = $formState->getValue('url_pattern');
      $pattern = explode('*.', $pattern)[1];

      // The host should contain exactly 4 parts. Subdomains of approved URL
      // patterns are not allowed, e.g. foo.bar.sites.uiowa.edu is invalid.
      $parts = explode('.', $url['host']);

      if (count($parts) != 4) {
        return $formState->setError(
          $element,
          t('URL @value must match the URL pattern *.@pattern.', [
            '@value' => $value,
            '@pattern' => $pattern,
          ])
        );
      }

      // Assuming the host contains exactly 4 parts, the last three should match
      // the pattern (minus the '*.' placeholder) when combined.
      $match = implode('.', [
        $parts[1],
        $parts[2],
        $parts[3],
      ]);

      if ($match != $pattern) {
        return $formState->setError(
          $element,
          t('URL @value must match the URL pattern *.@pattern.', [
            '@value' => $value,
            '@pattern' => $pattern,
          ])
        );
      }
    }

    // Validate that the URI does not already exist.
    if (file_exists(DRUPAL_ROOT . '/sites/' . $url['host'])) {
      return $formState->setError(
        $element,
        t('URL @value already exists. Please choose another.', [
          '@value' => $value,
        ])
      );
    }

    // Assuming the URI checks out, set some hidden fields based on it.
    $formState->setValue('url_host', $url['host']);
    $id = Multisite::getIdentifier($value);
    $formState->setValue('url_internal_production', "https://{$id}.prod.drupal.uiowa.edu");
  }

}
