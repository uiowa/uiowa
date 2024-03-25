<?php

namespace Drupal\brand_core\Commands;

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
class BrandCoreCommands extends DrushCommands {

  /**
   * Drush command constructor.
   *
   * @param \Drupal\Core\Session\AccountSwitcherInterface $accountSwitcher
   *   The account_switcher service.
   */
  public function __construct(protected AccountSwitcherInterface $accountSwitcher) {
    $this->accountSwitcher = $accountSwitcher;
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

    $message = brand_core_lockup_digest();
    $this->logger()->notice($message);
    if ($options['msg']) {
      $this->output()->writeln('Hey! Way to go above and beyond!');
    }

    // Switch user back.
    $this->accountSwitcher->switchBack();
  }

}
