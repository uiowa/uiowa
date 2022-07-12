<?php

namespace Drupal\sitenow_media_wysiwyg\Plugin\Validation\Constraint;

use Drupal\Component\Utility\UrlHelper;
use Drupal\sitenow_media_wysiwyg\Plugin\media\Source\StaticMap;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the StaticMapUrl constraint.
 */
class StaticMapURLConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    $value = $value->getValue()[0];
    $parsed_url = UrlHelper::parse($value['uri']);

    $no_id = !array_key_exists('id', $parsed_url['query']);
    $alt_text = $value['alt'];

    if (!str_starts_with($parsed_url['path'], StaticMap::BASE_URL)) {
      $this->context->addViolation($constraint->noBaseUrl, [
        '%base' => StaticMap::BASE_URL,
      ]);
    }

    if (!array_key_exists('fragment', $parsed_url) || !str_starts_with($parsed_url['fragment'], '!m/')) {
      $this->context->addViolation($constraint->noMarker, [
        '%marker' => '!m/',
      ]);
    }

    if ($no_id) {
      $this->context->addViolation($constraint->noId, [
        '%id' => '?id',
      ]);
    }

    if (empty($alt_text)) {
      $this->context->addViolation($constraint->noAltText);
    }

    $map_location = str_replace('!m/', '', $parsed_url['fragment']);
    $zoom_level = $value['zoom'];

    $url = 'https://staticmap.concept3d.com/map/static-map/?map=1890&loc=' . $map_location . '&scale=2&zoom=' . $zoom_level;
    $headers = get_headers($url, 1);
    $response = $headers[0];

    if ($response != 'HTTP/1.1 200 OK') {
      $this->context->addViolation($constraint->badResponse);
    }
  }

}
