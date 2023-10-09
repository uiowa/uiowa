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
 *   id = "brand_core",
 *   sub_types = {"lockup_review_digest" = @Translation("Lockup Review Digest")},
 * )
 */
class BrandLockupDigestEmailBuilder extends EmailBuilderBase {

  use MailerHelperTrait;
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function createParams(EmailInterface $email, array $lockups = NULL, string $label = NULL, string $results = NULL, string $login_url = NULL) {
    $email->setParam('lockups', $lockups)
      ->setParam('label', $label)
      ->setParam('results', $results)
      ->setParam('login_url', $login_url);
  }

  /**
   * {@inheritdoc}
   */
  public function build(EmailInterface $email) {
    $email->setTo($this->helper()->config()->get('system.site')->get('mail'));

    if ($email->getSubType() == 'lockup_review_digest') {
      $email->setSubject($this->t('Brand Manual - You have @results @label to review',
        [
          '@results' => $email->getParam('results'),
          '@label' => $email->getParam('label'),
        ]));

      $body = [];

      $body[] = $this->t('You have @results @label to review:', [
        '@results' => $email->getParam('results'),
        '@label' => $email->getParam('label'),
      ]);

      foreach ($email->getParam('lockups') as $lockup) {
        $body[] = $this->t('- @lockup', [
          '@lockup' => $lockup,
        ]);
      }

      $body[] = $this->t('@login', [
        '@login' => $email->getParam('login_url'),
      ]);

      $email->setBody(implode("\n", $body));
    }
  }

}
