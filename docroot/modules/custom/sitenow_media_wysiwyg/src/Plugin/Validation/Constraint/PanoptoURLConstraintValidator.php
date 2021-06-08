<?php

namespace Drupal\sitenow_media_wysiwyg\Plugin\Validation\Constraint;

use Drupal\Component\Utility\UrlHelper;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the PanoptoUrl constraint.
 */
class PanoptoURLConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {

    $value = $value->getValue();
    $value = reset($value);
    $uri = $value['uri'];
    $url = parse_url($uri);
    $parsed_url = UrlHelper::parse($uri);
    // Limit to UICapture and Panopto video type (id) for now.
    $no_id = !array_key_exists('id', $parsed_url['query']);
    if ($url['host'] !== 'uicapture.hosted.panopto.com' || $no_id) {
      // This doesn't properly target the URL/uri field.
      $this->context->buildViolation($constraint->message)
        ->atPath('uri')
        ->addViolation();
    }
  }

}
