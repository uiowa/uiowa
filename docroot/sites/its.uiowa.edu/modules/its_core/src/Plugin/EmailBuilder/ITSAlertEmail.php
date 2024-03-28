<?php

namespace Drupal\its_core\Plugin\EmailBuilder;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\symfony_mailer\Address as EmailAddress;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\MailerHelperTrait;
use Drupal\symfony_mailer\Processor\EmailBuilderBase;

/**
 * Defines an Email Builder for the ITS Alert emails.
 *
 * @EmailBuilder(
 *   id = "its_core",
 *   sub_types = {
 *    "its_alert_email" = @Translation("ITS Alert Email"),
 *    "its_alerts_digest" = @Translation("ITS Alerts Digest Email"),
 *   },
 * )
 */
class ITSAlertEmail extends EmailBuilderBase {

  use MailerHelperTrait;
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function createParams(EmailInterface $email, array $message = NULL) {
    $email->setParam('message', $message);
  }

  /**
   * {@inheritdoc}
   */
  public function build(EmailInterface $email) {
    $from = new EmailAddress('IT-Service-Alerts@uiowa.edu', 'IT Service Alerts');
    $email->setFrom($from);
    $email->setReplyTo($from);
    $env = getenv('AH_PRODUCTION');

    // Send the alerts digest.
    if ($email->getSubType() == 'its_alerts_digest') {
      $email->setSubject('IT Service Alerts Daily Digest');
      // Only send emails if PROD, otherwise use the site email for debugging.
      if ((int) $env === 1) {
        $email->setTo('IT-Service-Alerts-Members@iowa.uiowa.edu');
      }
      else {
        $email->setTo($this->helper()->config()->get('system.site')->get('mail'));
      }
    }

    // Send an individual alert.
    if ($email->getSubType() == 'its_alert_email') {

      // Only send emails if PROD, otherwise use the site email for debugging.
      if ((int) $env === 1) {
        $email->setTo('IT-Service-Alerts-Members@iowa.uiowa.edu');
        $email->setBcc('e7199078.iowa.onmicrosoft.com@amer.teams.ms');
      }
      else {
        $email->setTo($this->helper()->config()->get('system.site')->get('mail'));
      }
    }

    // Set body with message.
    $body = [];
    $markup = $email->getParam('message');

    // Sprinkle in some small CSS tweaks.
    $markup['styles'] = [
      '#type' => 'html_tag',
      '#tag' => 'style',
      '#value' => 'h1{font-size:18pt;}h2{font-size:16pt;}h3{font-size:14pt;}div{font-size:11pt;}',
    ];

    $body[] = [
      '#markup' => \Drupal::service('renderer')->render($markup),
    ];

    $email->setBody(['body' => $body]);
  }

}
