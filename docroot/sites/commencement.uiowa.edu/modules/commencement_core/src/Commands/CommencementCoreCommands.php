<?php

namespace Drupal\commencement_core\Commands;

use Drupal\commencement_core\EventsProcessor;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Session\UserSession;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\uiowa_core\Commands\CpuTimeTrait;
use Drush\Commands\DrushCommands;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 */
class CommencementCoreCommands extends DrushCommands {
  use CpuTimeTrait;
  use StringTranslationTrait;

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
   * @command commencement_core:events_import
   * @aliases commencement-events
   * @usage commencement_core:events_import
   *  Ideally this is done as a crontab that is only run once a day.
   */
  public function importEvents() {
    $this->initMeasurement();
    // Switch to the admin user to pass access check.
    $this->accountSwitcher->switchTo(new UserSession(['uid' => 1]));
    $this->logger()->notice($this->t("Starting the commencement event content sync from drush. This may take a little time if the information isn't cached."));

    $sync_service = new EventsProcessor();
    $success = $sync_service->process();

    if ($success) {
      $arguments = [
        '@created' => $sync_service->getCreated(),
        '@updated' => $sync_service->getUpdated(),
        '@deleted' => $sync_service->getDeleted(),
        '@skipped' => $sync_service->getSkipped(),
      ];
      $this->logger->notice($this->t('Commencement event content sync completed. @created events were created, @updated updated, @deleted deleted, @skipped skipped. That is neat.',
        $arguments));
    }
    else {
      $this->logger->warning($this->t('There was an error while processing the import for Commencement events. Please check logs or command line output for additional details.'));
    }

    // Switch user back.
    $this->accountSwitcher->switchBack();
    $this->finishMeasurment();
  }

}
