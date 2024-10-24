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
 *    "its_alert_email_secondary" = @Translation("ITS Alert Secondary Email"),
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
  public function createParams(EmailInterface $email, ?array $message = NULL) {
    $email->setParam('message', $message);
  }

  /**
   * {@inheritdoc}
   */
  public function build(EmailInterface $email) {
    $from = new EmailAddress('IT-Service-Alerts@uiowa.edu', 'IT Service Alerts');
    $email->setFrom($from);
    $email->setReplyTo($from);

    $its_settings = $this->helper()
      ->config()
      ->get('its_core.settings');

    // Send the alerts digest.
    if ($email->getSubType() == 'its_alerts_digest') {
      $email->setSubject('IT Service Alerts Daily Digest');
      $to_email = $its_settings->get('alert-digest');
      if (empty($to_email)) {
        return;
      }
      $email->setTo($to_email);
    }

    // Send an individual alert to the "To" recipient.
    if ($email->getSubType() == 'its_alert_email') {
      $to_email = $its_settings->get('single-alert-to');
      if (!empty($to_email)) {
        $email->setTo($to_email);
      }
    }

    // Send an individual alert to the "Bcc" recipient.
    if ($email->getSubType() == 'its_alert_email_secondary') {
      $secondary_email = $its_settings->get('single-alert-secondary');
      if (!empty($secondary_email)) {
        $email->setTo($secondary_email);
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
