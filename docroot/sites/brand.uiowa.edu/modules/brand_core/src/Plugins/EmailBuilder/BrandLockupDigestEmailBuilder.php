<?php

namespace Drupal\brand_core\Plugin\EmailBuilder;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\MailerHelperTrait;
use Drupal\symfony_mailer\Processor\EmailBuilderBase;

/**
 * Defines an Email Builder for the Lockup Digest email.
 *
 * @EmailBuilder(
 *   id = "brand_lockup_digest",
 *   sub_types = { },
 *   override = TRUE,
 *   common_adjusters = {"email_subject", "email_body", "email_to"},
 *   import = @Translation(""),
 * )
 */
class BrandLockupDigestEmailBuilder extends EmailBuilderBase {

  use MailerHelperTrait;
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function createParams(EmailInterface $email, array $lockups, string $label, string $results, string $login_url) {
    $email->setParam('lockups', $lockups)
      ->setParam('label', $label)
      ->setParam('results', $results)
      ->setParam('login_url', $login_url);
  }

  /**
   * {@inheritdoc}
   */
  public function build(EmailInterface $email) {
    $email->addTextHeader('Content-Type', 'text/html; charset=UTF-8; format=flowed; delsp=yes');

    $options = [
      'langcode' => $email->getLangcode(),
    ];

    if ($email->getSubType() == 'lockup-review-digest') {
      $email->setSubject($this->t('Brand Manual - You have @results @label to review',
        [
          '@results' => $email->getParam('results'),
          '@label' => $email->getParam('label'),
        ], $options));

      $body = [];

      $body[] = $this->t('You have @results @label to review:', [
        '@results' => $email->getParam('results'),
        '@label' => $email->getParam('label'),
      ], $options);

      foreach ($email->getParam('lockups') as $lockup) {
        $body[] = $this->t('- @lockup', ['@lockup' => $lockup], $options);
      }

      $body[] = $this->t('@login', ['@login' => $email->getParam('login_url')],
        $options);

      $email->setBody($body);
    }
  }

}
