<?php

namespace Drupal\facilities_core\Commands;

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
class FacilitiesCoreCommands extends DrushCommands {

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
   * Triggers the building import.
   *
   * @command facilities_core:buildings_import
   * @aliases fm-buildings
   * @usage facilities_core:buildings_import
   *  Ideally this is done as a crontab that is only run once a day.
   */
  public function importBuildings() {
    // Switch to the admin user to pass access check.
    $this->accountSwitcher->switchTo(new UserSession(['uid' => 1]));
    $this->logger()->notice('Starting the facilities building content sync from drush. This may take a little time if the information isn\'t cached.');

    $message = facilities_core_import_buildings();
    $this->logger()->notice($message);

    // Switch user back.
    $this->accountSwitcher->switchBack();
  }

  /**
   * Triggers the projects import.
   *
   * @command facilities_core:projects_import
   * @aliases fm-projects
   * @usage facilities_core:projects_import
   *  Ideally this is done as a crontab that is only run once a day.
   */
  public function importProjects() {
    // Switch to the admin user to pass access check.
    $this->accountSwitcher->switchTo(new UserSession(['uid' => 1]));
    $this->logger()->notice('Starting the facilities projects sync from drush. This may take a little time if the information isn\'t cached.');

    $message = facilities_core_import_projects();
    $this->logger()->notice($message);

    // Switch user back.
    $this->accountSwitcher->switchBack();
  }

}
