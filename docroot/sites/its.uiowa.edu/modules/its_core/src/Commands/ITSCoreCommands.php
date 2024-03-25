<?php

namespace Drupal\its_core\Commands;

use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Session\UserSession;
use Drush\Commands\DrushCommands;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 */
class ITSCoreCommands extends DrushCommands {

  /**
   * Drush command constructor.
   *
   * @param \Drupal\Core\Session\AccountSwitcherInterface $accountSwitcher
   *   The account_switcher service.
   */
  public function __construct(protected AccountSwitcherInterface $accountSwitcher) {
    parent::__construct();
  }

  /**
   * Triggers the Alerts Digest.
   *
   * @command its_core:alerts-digest
   * @aliases its-digest
   * @usage its_core:alerts-digest
   *  Ideally this is done as a crontab that is only sent once a day.
   */
  public function alertsDigest() {
    // Switch to the admin user to get hidden view result.
    $this->accountSwitcher->switchTo(new UserSession(['uid' => 1]));

    $this->logger()->notice('Alerts digest triggered via drush.');
    $message = its_core_alerts_digest();
    $this->logger()->notice($message);

    // Switch user back.
    $this->accountSwitcher->switchBack();
  }

}
