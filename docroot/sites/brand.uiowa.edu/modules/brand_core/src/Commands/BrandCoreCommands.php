<?php

namespace Drupal\brand_core\Commands;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Session\UserSession;
use Drush\Commands\DrushCommands;
use Drupal\Core\Url;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 */
class BrandCoreCommands extends DrushCommands {
  use LoggerChannelTrait;

  /**
   * The account_switcher service.
   *
   * @var \Drupal\Core\Session\AccountSwitcherInterface
   */
  protected $accountSwitcher;

  /**
   * The date_formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The plugin.manager.mail service.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The config.factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Drush command constructor.
   *
   * @param \Drupal\Core\Session\AccountSwitcherInterface $accountSwitcher
   *   The account_switcher service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date_formatter service.
   * @param \Drupal\Core\Mail\MailManagerInterface $mailManager
   *   The plugin.manager.mail service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config.factory service.
   */
  public function __construct(AccountSwitcherInterface $accountSwitcher, DateFormatterInterface $dateFormatter, MailManagerInterface $mailManager, ConfigFactoryInterface $configFactory) {
    $this->accountSwitcher = $accountSwitcher;
    $this->dateFormatter = $dateFormatter;
    $this->mailManager = $mailManager;
    $this->configFactory = $configFactory;
  }

  /**
   * Triggers the Lockup Digest.
   *
   * @command brand_core:lockup-digest
   * @aliases brand-ld
   * @options arr An option that takes multiple values.
   * @options msg Whether or not an extra message should be displayed to the user.
   * @usage brand_core:lockup-digest --msg
   *  Ideally this is done as a crontab that is only sent once a day.
   */
  public function lockupDigest($options = ['msg' => FALSE]) {
    // Switch to the admin user to get hidden view result.
    $this->accountSwitcher->switchTo(new UserSession(['uid' => 1]));
    $view = views_get_view_result('lockup_moderation', 'block_review');

    if (!empty($view)) {
      $results = count($view);
      $params['lockups'] = [];
      // Access field data from the view results.
      foreach ($view as $row) {
        $entity = $row->_entity;
        $timestamp = $entity->get('revision_timestamp');
        $date = $this->dateFormatter->format($timestamp->value, 'short', NULL, 'America/Chicago');
        $params['lockups'][] = $entity->getTitle() . ' - Last updated: ' . $date;
      }

      $label = $results > 1 ? 'lockups' : 'lockup';

      // Prepare params for digest email.
      $params['label'] = $label;
      $params['results'] = (string) $results;
      global $base_url;
      $url_options = [
        'query' => ['destination' => '/admin/content/lockups'],
      ];

      $params['login'] = Url::fromUri($base_url . '/saml/login', $url_options)->toString();
      $site_email = $this->configFactory->get('system.site')->get('mail');
      $result = $this->mailManager->mail('brand_core', 'lockup-review-digest', $site_email, 'en', $params, NULL, TRUE);

      if ($result['result'] !== TRUE) {
        $this->getLogger('brand_core')->error('Lockup Review Digest Not Sent');
        $this->output()->writeln('Lockup Review Digest Not Sent');
      }
      else {
        $this->getLogger('brand_core')->notice('Lockup Review Digest Sent');
        $this->output()->writeln('Lockup Review Digest Sent');
      }
    }
    else {
      $this->getLogger('brand_core')->notice('Lockup Review Digest - No items to review');
      $this->output()->writeln('Lockup Review Digest - No items to review');
      return;
    }

    if ($options['msg']) {
      $this->output()->writeln('Hey! Way to go above and beyond!');
    }

    // Switch user back.
    $this->accountSwitcher->switchBack();
  }

}
