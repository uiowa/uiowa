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

    // Send the alerts digest.
    if ($email->getSubType() == 'its_alerts_digest') {
      $email->setSubject('IT Service Alerts Daily Digest');
      // Grab the alerts digest email to send to. Typically,
      // in production this would be
      // IT-Service-Alerts-Members@iowa.uiowa.edu.
      $to_email = $this->helper()->config()->get('its_core.settings')->get('alert-digest');
      // If we don't have an email set,
      // then exit here because nothing should be sent.
      if (empty($to_email)) {
        return;
      }
      $email->setTo($to_email);
    }

    // Send an individual alert.
    if ($email->getSubType() == 'its_alert_email') {
      // Grab the individual alerts email to send to. Typically,
      // in production this would be
      // IT-Service-Alerts-Members@iowa.uiowa.edu for TO and
      // e7199078.iowa.onmicrosoft.com@amer.teams.ms as BCC.
      $to_email = $this->helper()->config()->get('its_core.settings')->get('single-alert-to');
      $bcc_email = $this->helper()->config()->get('its_core.settings')->get('single-alert-bcc');
      // If we don't have an email set for either TO or BCC,
      // then exit here because nothing should be sent.
      if (empty($to_email) && empty($bcc_email)) {
        return;
      }
      $email->setTo($to_email);
      $email->setBcc($bcc_email);
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
