<?php

namespace Drupal\sitenow_media_wysiwyg\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Check if a value is a valid URL.
 *
 * @Constraint(
 *   id = "PanoptoURL",
 *   label = @Translation("Panopto Url", context = "Validation"),
 *   type = { "link", "string", "string_long" }
 * )
 */
class PanoptoURLConstraint extends Constraint {
  /**
   * The message shown when the value does not start with the Panopto base URL.
   */
  public string $noBaseUrl = 'The URL must start with %base.';

  /**
   * The message shown when the value does not include an ID.
   */
  public string $noId = 'The URL must include an %id parameter.';

}
