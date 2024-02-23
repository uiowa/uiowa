<?php

namespace Drupal\its_core\Plugin\EmailBuilder;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\symfony_mailer\Address as EmailAddress;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\MailerHelperTrait;
use Drupal\symfony_mailer\Processor\EmailBuilderBase;

/**
 * Defines an Email Builder for the Lockup Digest email.
 *
 * @EmailBuilder(
 *   id = "its_core",
 *   sub_types = {"its_alert_email" = @Translation("ITS Alert Email")},
 * )
 */
class ITSAlertEmail extends EmailBuilderBase {

  use MailerHelperTrait;
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function createParams(EmailInterface $email, array $alert = NULL) {
    $email->setParam('alert', $alert);
  }

  /**
   * {@inheritdoc}
   */
  public function build(EmailInterface $email) {
    if ($email->getSubType() == 'its_alert_email') {

      $from = new EmailAddress('IT-Service-Alerts@uiowa.edu', 'IT Service Alerts');
      $email->setFrom($from);
      $email->setReplyTo($from);
      $env = getenv('AH_PRODUCTION');
      if ((int) $env === 1) {
        // @todo After testing, should be
        // "e7199078.iowa.onmicrosoft.com@amer.teams.ms,
        // IT-Service-Alerts-Members@iowa.uiowa.edu".
        $email->setBcc('its-web@uiowa.edu');
      }
      else {
        // @todo After testing, use site email to avoid unintentional emails?
        $email->setBcc('joe-whitsitt@uiowa.edu');
      }

      $body = [];
      $markup = $email->getParam('alert');

      $body[] = [
        '#markup' => \Drupal::service('renderer')->render($markup),
      ];

      $email->setBody(['body' => $body]);
    }
  }

}
