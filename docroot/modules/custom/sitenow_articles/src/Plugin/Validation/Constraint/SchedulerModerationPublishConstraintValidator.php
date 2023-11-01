<?php

namespace Drupal\sitenow_articles\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the SchedulerModerationPublish constraint.
 */
class SchedulerModerationPublishConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    $entity = $value->getEntity();
    $moderation_state = $entity->moderation_state->value;
    $publish_state = $entity->publish_state->value;
    if ($moderation_state === $publish_state) {
      $this->context->buildViolation($constraint->errorMessage)
        ->atPath('moderation_state')
        ->addViolation();
    }

  }

}
