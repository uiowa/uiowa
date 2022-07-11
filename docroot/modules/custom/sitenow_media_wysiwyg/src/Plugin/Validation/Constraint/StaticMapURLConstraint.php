<?php

namespace Drupal\sitenow_media_wysiwyg\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Check if a value is a valid URL.
 *
 * @Constraint(
 *   id = "StaticMapUrl",
 *   label = @Translation("Static Map Url", context = "Validation"),
 *   type = { "link", "string", "string_long" }
 * )
 */
class StaticMapURLConstraint extends Constraint {
  /**
   * The message shown when the value does not start with the maps.uiowa.edu base URL.
   *
   * @var string
   */
  public $noBaseUrl = 'The URL must start with %base.';

  /**
   * The message shown when the value does not include an ID.
   *
   * @var string
   */
  public $noId = 'The URL must include an %id parameter.';

  /**
   * The message shown when the value does not include a marker.
   *
   * @var string
   */
  public $noMarker = 'The URL must include an %marker.';

  /**
   * The message shown when the response is not valid.
   *
   * @var string
   */
  public $badResponse = 'The URL cannot return a server error message.';

}
