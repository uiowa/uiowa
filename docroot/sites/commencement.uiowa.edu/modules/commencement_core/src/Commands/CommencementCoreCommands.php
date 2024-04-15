<?php

namespace Drupal\commencement_core\Commands;

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
class CommencementCoreCommands extends DrushCommands {

  /**
   * The account_switcher service.
   *
   * @var \Drupal\Core\Session\AccountSwitcherInterface
   */
  protected $accountSwitcher;

  /**
   * A nullable array of data returned by the API.
   *
   * @var array|null
   */
  protected ?array $data;

  /**
   * Drush command constructor.
   *
   * @param \Drupal\Core\Session\AccountSwitcherInterface $accountSwitcher
   *   The account_switcher service.
   */
  public function __construct(AccountSwitcherInterface $accountSwitcher) {
    $this->accountSwitcher = $accountSwitcher;
  }

  /**
   * Triggers the event import.
   *
   * @command commencement_core:events_import
   * @aliases contenthub-events
   * @usage comemencement_core:events_import
   */
  public function importEvents() {
    // Switch to the admin user to pass access check.
    $this->accountSwitcher->switchTo(new UserSession(['uid' => 1]));
    $this->logger()->notice("Starting the event content sync from drush. This may take a little time if the information isn't cached.");

    $message = commencement_core_import_events();
    $this->logger()->notice($message);

    // Switch user back.
    $this->accountSwitcher->switchBack();
  }

}
