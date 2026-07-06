<?php

namespace Drupal\forecords_core;

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Session\UserSession;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\views_data_export\Plugin\views\display\DataExport;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class RecordsExport.
 *
 * Provides functionality to handle records export file operations.
 */
class RecordsExport {
  use LoggerChannelTrait;
  use StringTranslationTrait;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * The account switcher service.
   *
   * @var \Drupal\Core\Session\AccountSwitcherInterface
   */
  protected AccountSwitcherInterface $accountSwitcher;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file_system service.
   * @param \Drupal\Core\Session\AccountSwitcherInterface $accountSwitcher
   *   The account_switcher service.
   */
  public function __construct(FileSystemInterface $fileSystem, AccountSwitcherInterface $accountSwitcher) {
    $this->fileSystem = $fileSystem;
    $this->accountSwitcher = $accountSwitcher;
  }

  /**
   * Generate the records export file.
   *
   * @return bool
   *   Returns TRUE if the file was successfully generated, FALSE otherwise.
   */
  public function generateRecordsExport(): bool {
    $public_files = $this->fileSystem->realpath('public://exports/');
    $file_name = 'records.csv';
    $file_path = $public_files . '/' . $file_name;
    $this->fileSystem->prepareDirectory($public_files, FileSystemInterface::CREATE_DIRECTORY);

    // Switch to user 1 to run trigger the view.
    $this->accountSwitcher->switchTo(new UserSession(['uid' => 1]));
    $result = DataExport::buildResponse('record', 'data_export_records');
    $this->accountSwitcher->switchBack();

    if (!$result instanceof Response) {
      $this->getLogger('forecords_core')->notice($this->t('Failed to generate records download.'));
      return FALSE;
    }

    // Save the response content to the specified output file
    // replacing the file if it already exists.
    $this->fileSystem->saveData($result->getContent(), $file_path, FileExists::Replace);
    $this->getLogger('forecords_core')->notice($this->t('Generated @file', ['@file' => $file_name]));
    return TRUE;
  }

}
