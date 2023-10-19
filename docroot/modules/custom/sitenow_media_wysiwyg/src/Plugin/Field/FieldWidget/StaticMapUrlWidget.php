<?php

namespace Drupal\sitenow_media_wysiwyg\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\link\Plugin\Field\FieldWidget\LinkWidget;
use Drupal\sitenow_media_wysiwyg\Plugin\Field\FieldType\StaticMapUrl;
use Drupal\sitenow_media_wysiwyg\Plugin\media\Source\StaticMap;

/**
 * Static Map URL field widget.
 *
 * @FieldWidget(
 *   id = "static_map_url_widget",
 *   label = @Translation("Static Map URL"),
 *   description = @Translation("A field for a static map url."),
 *   field_types = {
 *    "static_map_url"
 *   },
 * )
 */
class StaticMapUrlWidget extends LinkWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {

    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $element['zoom'] = [
      '#type' => 'select',
      '#title' => $this->t('Zoom'),
      '#description' => $this->t('The higher the number the more zoomed in the map will be.'),
      '#options' => ['' => $this->t('- Select a value -')] + StaticMapUrl::allowedZoomValues(),
      '#default_value' => $items[$delta]->zoom ?? 17,
      '#required' => TRUE,
    ];

    $element['alt'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Alternative text'),
      '#default_value' => $items[$delta]->alt ?? NULL,
      '#maxlength' => 255,
      '#description' => $this->t('Short description of the static map image used by screen readers and displayed when the static map image is not loaded. This is important for accessibility.'),
      '#required' => TRUE,
    ];

    $element['uri']['#element_validate'][] = [
      get_called_class(), 'uriValidation',
    ];

    return $element;
  }

  /**
   * Validates the static map URL.
   */
  public static function uriValidation(&$element, $form_state): void {
    $value = $element['#value'];
    $parsed_url = UrlHelper::parse($value);

    if (!str_starts_with($parsed_url['path'], StaticMap::BASE_URL)) {
      $form_state->setError($element, t('The URL must start with @base.', [
        '@base' => StaticMap::BASE_URL,
      ]));
    }

    $no_id = !array_key_exists('id', $parsed_url['query']);
    if ($no_id) {
      $form_state->setError($element, t('The URL must include an @id parameter.', [
        '@id' => '?id',
      ]));
    }

    if (!array_key_exists('fragment', $parsed_url) || !str_starts_with($parsed_url['fragment'], '!m/')) {
      $form_state->setError($element, t('The URL must include an @marker.', [
        '@marker' => '!m/',
      ]));
    }

    // Construct URL like the formatter to test the response from Concept3D.
    $map_location = str_replace('!m/', '', $parsed_url['fragment']);
    $url = StaticMap::STATIC_URL . '/map/static-map/?map=1890&loc=' . $map_location . '&scale=2&zoom=17';
    $headers = get_headers($url, 1);
    $response = $headers[0];

    if ($response != 'HTTP/1.1 200 OK') {
      $form_state->setError($element, t('The URL must return a valid HTTP status code.'));
    }
  }

}
