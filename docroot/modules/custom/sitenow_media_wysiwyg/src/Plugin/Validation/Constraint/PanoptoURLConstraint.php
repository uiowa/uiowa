<?php

namespace Drupal\sitenow_media_wysiwyg\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Check if a value is a valid URL.
 *
 * @constraint(
 *   id = "PanoptoURL",
 *   label = @Translation("Panopto Url", context = "Validation"),
 *   type = { "link", "string", "string_long" }
 * )
 */
class PanoptoURLConstraint extends Constraint {
  /**
   * The default violation message.
   *
   * @var string
   */
  public $message = 'Not a valid URL.';

}
