<?php

namespace Drupal\uiowa_core;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides helpers for sanitizing link analytics attributes on block forms.
 */
class LinkAnalyticsHelper {

  /**
   * Sanitizes analytics attributes on a link field in a layout builder block.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $field_name
   *   The link field machine name.
   * @param string $component_type
   *   The component type (e.g. 'button', 'link').
   */
  public static function sanitizeLinkAnalyticsAttributes(FormStateInterface $form_state, string $field_name, string $component_type): void {
    $links = $form_state->getValue(['settings', 'block_form', $field_name]) ?? [];
    foreach ($links as $key => $link) {
      if (!is_array($link)) {
        continue;
      }
      $fallback_label = trim($link['title'] ?? '');
      $attributes = $link['options']['attributes'] ?? [];
      $attributes = self::sanitizeAttributes($attributes, $component_type, $fallback_label);
      $form_state->setValue([
        'settings',
        'block_form',
        $field_name,
        $key,
        'options',
        'attributes',
      ], $attributes);
    }
  }

  /**
   * Sanitizes analytics attributes on a menu_link_content form.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $component_type
   *   The component type (e.g. 'menu-link').
   */
  public static function sanitizeMenuLinkAnalyticsAttributes(FormStateInterface $form_state, string $component_type): void {
    $attributes = $form_state->getValue(['link', 0, 'options', 'attributes']) ?? [];
    $fallback_label = trim($form_state->getValue(['title', 0, 'value']) ?? '');
    $attributes = self::sanitizeAttributes($attributes, $component_type, $fallback_label);
    $form_state->setValue(['link', 0, 'options', 'attributes'], $attributes);
  }

  /**
   * Core sanitization logic shared across link contexts.
   *
   * @param array $attributes
   *   The current link attributes array.
   * @param string $component_type
   *   The component type string.
   * @param string $fallback_label
   *   A pre-trimmed fallback label (e.g. link title or menu link title).
   *
   * @return array
   *   The sanitized attributes array.
   */
  private static function sanitizeAttributes(array $attributes, string $component_type, string $fallback_label): array {
    $event_name = trim($attributes['data-sn-event'] ?? '');
    if ($event_name === '') {
      unset($attributes['data-sn-event']);
      unset($attributes['data-sn-event-type']);
      unset($attributes['data-sn-event-component']);
      unset($attributes['data-sn-event-label']);
    }
    else {
      $event_name = strtolower(Html::cleanCssIdentifier($event_name));
      $attributes['data-sn-event'] = str_replace('-', '_', $event_name);
      if (empty(trim($attributes['data-sn-event-label'] ?? '')) && $fallback_label !== '') {
        $attributes['data-sn-event-label'] = $fallback_label;
      }
      $attributes['data-sn-event-component'] = $component_type;
    }
    return $attributes;
  }

}
