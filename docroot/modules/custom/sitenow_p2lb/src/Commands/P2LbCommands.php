<?php

namespace Drupal\sitenow_p2lb\Commands;

use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Session\UserSession;
use Drush\Commands\DrushCommands;

/**
 * A Drush command file for sitenow_p2lb.
 */
class P2LbCommands extends DrushCommands {

  /**
   * The account_switcher service.
   *
   * @var \Drupal\Core\Session\AccountSwitcherInterface
   */
  protected $accountSwitcher;

  /**
   * Command constructor.
   */
  public function __construct(AccountSwitcherInterface $accountSwitcher) {
    $this->accountSwitcher = $accountSwitcher;
  }

  /**
   * Clean up and remove v2/P2LB.
   *
   * @param array $options
   *   Additional options for the command.
   *
   * @command sitenow_p2lb:cleanup
   *
   * @option batch The batch size
   * @aliases p2lb-cleanup
   * @usage sitenow_p2lb:cleanup
   *  Ideally run during the finishing process.
   * @usage sitenow_p2lb:cleanup --batch=5
   *  Process nodes with a specified batch size.
   */
  public function cleanup(array $options = ['batch' => 5]) {
    // Switch to the admin user to pass access check.
    $this->accountSwitcher->switchTo(new UserSession(['uid' => 1]));

    $message = sitenow_p2lb_cleanup($options['batch']);
    $this->logger()->notice($message);

    // Switch user back.
    $this->accountSwitcher->switchBack();
  }

}
