<?php

namespace Drupal\forecords_core\Commands;

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Session\UserSession;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\views_data_export\Plugin\views\display\DataExport;
use Drush\Commands\DrushCommands;
use Symfony\Component\HttpFoundation\Response;

/**
 * A Drush commandfile.
 */
class FORecordsCoreCommands extends DrushCommands {
  use LoggerChannelTrait;
  use StringTranslationTrait;

  /**
   * Drush command constructor.
   *
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file_system service.
   * @param \Drupal\Core\Session\AccountSwitcherInterface $accountSwitcher
   *   The account_switcher service.
   */
  public function __construct(
    protected FileSystemInterface $fileSystem,
    protected AccountSwitcherInterface $accountSwitcher,
  ) {
    parent::__construct();
  }

  /**
   * Triggers Records Download.
   *
   * @command forecords_core:records-download
   * @aliases records-download
   * @usage forecords_core:records-download
   *  Inspired by views_data_export:views-data-export.
   */
  public function recordsDownload(): void {
    $public_files = $this->fileSystem->realpath('public://exports/');
    $file_name = 'records_' . date('Y-m-d') . '.csv';
    $file_path = $public_files . '/' . $file_name;
    $directory = $this->fileSystem->dirname($file_path);
    $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);

    // Check for older files of type csv and delete them based on file date.
    $files = $this->fileSystem->scanDirectory($public_files, '/records_.*\.csv/',
      [
        'recurse' => FALSE,
        'key' => 'filename',
      ]);

    foreach ($files as $file) {
      // Use filemtime to get the last modified time of the file.
      $file_mtime = filemtime($file->uri);
      // If the file is older than 1 day, delete it.
      // @todo set to -1 day after testing.
      if ($file_mtime < strtotime('-1 minutes')) {
        $this->fileSystem->delete($file->uri);
        $this->getLogger('forecords_core')->notice($this->t('Deleted old file: @file', ['@file' => $file->filename]));
      }
    }

    // Exit if the file already exists.
    if (file_exists($file_path)) {
      $this->getLogger('forecords_core')->notice(
        $this->t('Records download for today already exists: @file', ['@file' => $file_name])
      );
      return;
    }

    // Switch to user 1 to run trigger the view.
    $this->accountSwitcher->switchTo(new UserSession(['uid' => 1]));
    $result = DataExport::buildResponse('record', 'data_export_records');
    $this->accountSwitcher->switchBack();
    if ($result instanceof Response) {
      // Save the response content to the specified output file.
      $this->fileSystem->saveData($result->getContent(), $file_path, FileExists::Replace);
      $this->getLogger('forecords_core')->notice($this->t('Generated @file', ['@file' => $file_name]));
    }
    else {
      $this->getLogger('forecords_core')->notice($this->t('Failed to generate records download.'));
    }
  }

}
