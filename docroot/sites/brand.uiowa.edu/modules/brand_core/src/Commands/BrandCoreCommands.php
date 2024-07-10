<?php

namespace Drupal\brand_core\Commands;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Session\UserSession;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\symfony_mailer\EmailFactoryInterface;
use Drush\Commands\DrushCommands;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 */
class BrandCoreCommands extends DrushCommands {
  use LoggerChannelTrait;
  use StringTranslationTrait;

  /**
   * Drush command constructor.
   *
   * @param \Drupal\Core\Session\AccountSwitcherInterface $accountSwitcher
   *   The account_switcher service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date_formatter service.
   * @param \Drupal\symfony_mailer\EmailFactoryInterface $emailFactory
   *   The plugin.manager.mail service.
   */
  public function __construct(protected AccountSwitcherInterface $accountSwitcher, protected DateFormatterInterface $dateFormatter, protected EmailFactoryInterface $emailFactory) {
    $this->accountSwitcher = $accountSwitcher;
    $this->dateFormatter = $dateFormatter;
  }

  /**
   * Triggers the Lockup Digest.
   *
   * @command brand_core:lockup-digest
   * @aliases brand-ld
   * @options arr An option that takes multiple values.
   * @options msg Whether an extra message should be displayed to the user.
   * @usage brand_core:lockup-digest --msg
   *  Ideally this is done as a crontab that is only sent once a day.
   */
  public function lockupDigest($options = ['msg' => FALSE]) {
    // Switch to the admin user to get hidden view result.
    $this->accountSwitcher->switchTo(new UserSession(['uid' => 1]));
    $view = views_get_view_result('lockup_moderation', 'block_review');

    if (!empty($view)) {
      $results = count($view);
      $lockups = [];
      // Access field data from the view results.
      foreach ($view as $row) {
        $entity = $row->_entity;
        $timestamp = $entity->get('revision_timestamp');
        $date = $this->dateFormatter->format($timestamp->value, 'short', NULL, 'America/Chicago');
        $lockups[] = $entity->getTitle() . ' - Last updated: ' . $date;
      }

      $label = $results > 1 ? 'lockups' : 'lockup';

      // Prepare params for digest email.
      $results = (string) $results;
      global $base_url;
      $url_options = [
        'query' => ['destination' => '/admin/content/lockups'],
      ];

      $login_url = Url::fromUri($base_url . '/saml/login', $url_options)->toString();
      $email = $this->emailFactory->sendTypedEmail('brand_core', 'lockup_review_digest', $lockups, $label, $results, $login_url);

      if ($email->getError()) {
        $this->getLogger('brand_core')->error($this->t('Lockup Review Digest not sent. Error: @error'));
      }
      else {
        $this->getLogger('brand_core')->notice($this->t('Lockup Review Digest sent'));
      }
    }
    else {
      $this->getLogger('brand_core')->notice($this->t('Lockup Review Digest - No items to review'));
      return;
    }

    if ($options['msg']) {
      $this->output()->writeln('Hey! Way to go above and beyond!');
    }

    // Switch user back.
    $this->accountSwitcher->switchBack();
  }

}
