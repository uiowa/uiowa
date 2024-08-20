<?php

namespace Drupal\facilities_core\Commands;

use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Session\UserSession;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\facilities_core\BuildingsProcessor;
use Drupal\facilities_core\ProjectsProcessor;
use Drupal\uiowa_core\Commands\CpuTimeTrait;
use Drush\Commands\DrushCommands;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 */
class FacilitiesCoreCommands extends DrushCommands {
  use CpuTimeTrait;
  use StringTranslationTrait;
  use LoggerChannelTrait;

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
    $this->initMeasurement();
    // Switch to the admin user to pass access check.
    $this->accountSwitcher->switchTo(new UserSession(['uid' => 1]));
    $this->getLogger('facilities_core')->notice($this->t("Starting the facilities building content sync from drush. This may take a little time if the information isn't cached."));

    $sync_service = new BuildingsProcessor();
    $success = $sync_service->process();

    if ($success) {
      $arguments = [
        '@created' => $sync_service->getCreated(),
        '@updated' => $sync_service->getUpdated(),
        '@deleted' => $sync_service->getDeleted(),
        '@skipped' => $sync_service->getSkipped(),
      ];
      $this->getLogger('facilities_core')->notice($this->t('Facilities building content sync completed. @created buildings were created, @updated updated, @deleted deleted, @skipped skipped. That is neat.',
        $arguments));
    }
    else {
      $this->getLogger('facilities_core')->warning($this->t('There was an error while processing the import for Facilities buildings. Please check logs or command line output for additional details.'));
    }

    // Switch user back.
    $this->accountSwitcher->switchBack();
    $this->finishMeasurment();
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
    $this->initMeasurement();

    // Switch to the admin user to pass access check.
    $this->accountSwitcher->switchTo(new UserSession(['uid' => 1]));
    $this->getLogger('facilities_core')->notice($this->t("Starting the facilities projects sync from drush. This may take a little time if the information isn't cached."));

    $sync_service = new ProjectsProcessor();
    $success = $sync_service->process();
    if ($success) {
      $arguments = [
        '@created' => $sync_service->getCreated(),
        '@updated' => $sync_service->getUpdated(),
        '@deleted' => $sync_service->getDeleted(),
        '@skipped' => $sync_service->getSkipped(),
      ];
      $this->getLogger('facilities_core')->notice($this->t('Facilities projects content sync completed. @created projects were created, @updated updated, @deleted deleted, @skipped skipped. That is neat.', $arguments));
    }
    else {
      $this->getLogger('facilities_core')->warning($this->t('There was an error while processing the import for Facilities projects. Please check logs or command line output for additional details.'));
    }

    // Switch user back.
    $this->accountSwitcher->switchBack();
    $this->finishMeasurment();
  }

}
