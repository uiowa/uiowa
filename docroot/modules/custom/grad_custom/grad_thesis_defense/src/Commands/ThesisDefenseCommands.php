<?php

namespace Drupal\grad_thesis_defense\Commands;

use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Session\UserSession;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\grad_thesis_defense\ThesisDefenseProcessor;
use Drupal\uiowa_core\Commands\CpuTimeTrait;
use Drush\Commands\DrushCommands;

/**
 * A Drush commandfile for thesis defense processing.
 */
class ThesisDefenseCommands extends DrushCommands {
  use CpuTimeTrait;
  use StringTranslationTrait;

  /**
   * The account_switcher service.
   *
   * @var \Drupal\Core\Session\AccountSwitcherInterface
   */
  protected $accountSwitcher;

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
   * Triggers the thesis defense import.
   *
   * @command import:thesis-defense
   * @aliases import-thesis-defense
   * @usage import:thesis-defense
   *  Triggers the thesis defense content sync from drush.
   */
  public function importThesisDefense() {
    $this->initMeasurement();
    // Switch to the admin user to pass access check.
    $this->accountSwitcher->switchTo(new UserSession(['uid' => 1]));
    $this->logger()->notice($this->t("Starting the thesis defense content sync from drush. This may take a little time if the information isn't cached."));

    $sync_service = new ThesisDefenseProcessor();
    $success = $sync_service->process();

    if ($success) {
      $arguments = [
        '@created' => $sync_service->getCreated(),
        '@updated' => $sync_service->getUpdated(),
        '@deleted' => $sync_service->getDeleted(),
        '@skipped' => $sync_service->getSkipped(),
      ];
      $this->logger->notice($this->t('Thesis defense content sync completed. @created items were created, @updated updated, @deleted deleted, @skipped skipped. That is neat.',
        $arguments));
    }
    else {
      $this->logger->warning($this->t('There was an error while processing the import for thesis defense items. Please check logs or command line output for additional details.'));
    }

    // Switch user back.
    $this->accountSwitcher->switchBack();
    $this->finishMeasurment();
  }

}
