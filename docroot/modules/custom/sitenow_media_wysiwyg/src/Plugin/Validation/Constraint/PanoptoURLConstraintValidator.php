<?php

namespace Drupal\sitenow_media_wysiwyg\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the PanoptoUrl constraint.
 */
class PanoptoUrlConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {

    $value = $value->getValue();
    $value = reset($value);
    $uri = $value['uri'];
    $url = parse_url($uri);

    if ($url['host'] !== 'uicapture.hosted.panopto.com') {
      // This doesn't properly target the URL/uri field.
      $this->context->buildViolation($constraint->message)
        ->atPath('uri')
        ->addViolation();
    }
  }

}
