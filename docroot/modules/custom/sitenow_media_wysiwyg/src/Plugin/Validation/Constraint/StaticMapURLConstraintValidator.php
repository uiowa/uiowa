<?php

/**
 * @file
 */

// Namespace Drupal\sitenow_media_wysiwyg\Plugin\Validation\Constraint;
//
// use Drupal\Component\Utility\UrlHelper;
// use Drupal\sitenow_media_wysiwyg\Plugin\media\Source\StaticMap;
// use Symfony\Component\Validator\Constraint;
// use Symfony\Component\Validator\ConstraintValidator;
//
// **
// * Validates the StaticMapUrl constraint.
// */
// class StaticMapURLConstraintValidator extends ConstraintValidator {
//
//  /**
//   * {@inheritdoc}
//   */
//  public function validate($value, Constraint $constraint) {
//    $value = $value->getValue()[0];
//    $parsed_url = UrlHelper::parse($value['uri']);
//
//    // Limit to Static Map type (id) for now.
//    $no_id = !array_key_exists('id', $parsed_url['query']);
//
//    if (!str_starts_with($parsed_url['path'], StaticMap::BASE_URL)) {
//      $this->context->addViolation($constraint->noBaseUrl, [
//        '%base' => StaticMap::BASE_URL,
//      ]);
//    }
//
//    if ($no_id) {
//      $this->context->addViolation($constraint->noId, [
//        '%id' => '?id',
//      ]);
//    }
//  }
//
// }.
