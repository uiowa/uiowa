<?php

namespace Drupal\sitenow_articles\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Provides a SchedulerModerationPublish constraint.
 *
 * @Constraint(
 *   id = "SiteNowArticlesSchedulerModerationPublish",
 *   label = @Translation("SchedulerModerationPublish", context = "Validation"),
 * )
 */
class SchedulerModerationPublishConstraint extends Constraint {

  public $errorMessage = 'Scheduling criteria conflicts with the moderation state.';

}
