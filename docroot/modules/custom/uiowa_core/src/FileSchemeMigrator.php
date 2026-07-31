<?php

namespace Drupal\uiowa_core;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StreamWrapper\PublicStream;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\file\FileInterface;
use Drupal\file\FileRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Moves managed files between the public and private stream wrappers.
 *
 * Changing a field's uri_scheme only affects where new uploads land. Files
 * already referenced keep their original URI, so a site that switches to
 * private storage keeps serving its existing files from the public directory
 * until they are moved. This service does the moving.
 */
class FileSchemeMigrator {

  /**
   * Schemes this service is willing to move files between.
   */
  const SUPPORTED_SCHEMES = ['public', 'private'];

  /**
   * Field types that can hold a hard-coded file path, keyed to their column.
   *
   * The column is the suffix appended to the field name in the field's data
   * table. Layout builder sections are serialized into a text column, so a
   * path pasted into a block's configuration is caught there rather than in
   * any block_content table.
   */
  const SCANNED_FIELD_TYPES = [
    'text' => 'value',
    'text_long' => 'value',
    'text_with_summary' => 'value',
    'string_long' => 'value',
    'layout_section' => 'section',
    'link' => 'uri',
  ];

  /**
   * Constructs a FileSchemeMigrator.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected FileSystemInterface $fileSystem,
    protected FileRepositoryInterface $fileRepository,
    protected StreamWrapperManagerInterface $streamWrapperManager,
    protected Connection $database,
    protected LoggerInterface $logger,
  ) {}

  /**
   * Checks whether a scheme can be written to.
   *
   * @param string $scheme
   *   The scheme to check, without the '://' suffix.
   *
   * @return string|null
   *   An operator-facing reason the scheme is unusable, or NULL if it is fine.
   */
  public function checkScheme(string $scheme): ?string {
    if (!in_array($scheme, static::SUPPORTED_SCHEMES, TRUE)) {
      return sprintf("'%s' is not a supported scheme.", $scheme);
    }

    if (!$this->streamWrapperManager->isValidScheme($scheme)) {
      // The private wrapper is only valid once file_private_path is set, which
      // on Acquia comes from the hosting settings include.
      return sprintf("The '%s://' stream wrapper is not registered. Check that file_private_path is set for this site.", $scheme);
    }

    $root = $scheme . '://';

    if (!$this->fileSystem->prepareDirectory($root, FileSystemInterface::CREATE_DIRECTORY)) {
      return sprintf("'%s' exists but is not writable.", $root);
    }

    return NULL;
  }

  /**
   * Counts managed files stored on a scheme.
   *
   * @param string $scheme
   *   The scheme to count, without the '://' suffix.
   *
   * @return int
   *   The number of managed files whose URI uses this scheme.
   */
  public function countFiles(string $scheme): int {
    return (int) $this->fileQuery($scheme)->count()->execute();
  }

  /**
   * Loads the IDs of managed files stored on a scheme.
   *
   * @param string $scheme
   *   The scheme to search, without the '://' suffix.
   *
   * @return int[]
   *   The matching file IDs, ordered by ID so chunked runs stay predictable.
   */
  public function getFileIds(string $scheme): array {
    return array_values($this->fileQuery($scheme)->sort('fid')->execute());
  }

  /**
   * Moves a single managed file to another scheme.
   *
   * The file entity keeps its ID, so every entity reference and file usage
   * record pointing at it survives the move untouched.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file to move.
   * @param string $to
   *   The destination scheme, without the '://' suffix.
   *
   * @return string
   *   The file's new URI.
   *
   * @throws \Drupal\Core\File\Exception\FileException
   *   If the destination directory cannot be created or the move fails.
   */
  public function moveFile(FileInterface $file, string $to): string {
    $uri = $file->getFileUri();
    $target = $this->streamWrapperManager->getTarget($uri);
    $destination = $to . '://' . $target;
    $directory = $this->fileSystem->dirname($destination);

    $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
    $moved = $this->fileRepository->move($file, $destination, FileExists::Error);

    return $moved->getFileUri();
  }

  /**
   * Moves every managed file from one scheme to another.
   *
   * @param string $from
   *   The source scheme, without the '://' suffix.
   * @param string $to
   *   The destination scheme, without the '://' suffix.
   * @param bool $dry_run
   *   Report what would move without moving anything.
   * @param callable|null $progress
   *   Optional callback invoked as callback(string $outcome, int $fid, string
   *   $detail) after each file, where $outcome is 'moved', 'missing' or
   *   'failed'.
   *
   * @return array
   *   An array keyed 'moved', 'missing' and 'failed'. Each value is an array
   *   keyed by file ID; 'moved' holds destination URIs, the others hold
   *   operator-facing reasons.
   */
  public function migrate(string $from, string $to, bool $dry_run = FALSE, ?callable $progress = NULL): array {
    $results = ['moved' => [], 'missing' => [], 'failed' => []];
    $storage = $this->entityTypeManager->getStorage('file');

    foreach (array_chunk($this->getFileIds($from), 50) as $chunk) {
      foreach ($storage->loadMultiple($chunk) as $file) {
        $uri = $file->getFileUri();
        $fid = (int) $file->id();

        // A managed file with no file on disk cannot be moved. Report it
        // rather than failing the run, so one bad row does not block the rest.
        if (!file_exists($uri)) {
          $results['missing'][$fid] = $uri;
          $this->report($progress, 'missing', $fid, $uri);
          continue;
        }

        if ($dry_run) {
          $destination = $to . '://' . $this->streamWrapperManager->getTarget($uri);
          $results['moved'][$fid] = $destination;
          $this->report($progress, 'moved', $fid, $destination);
          continue;
        }

        try {
          $destination = $this->moveFile($file, $to);
          $results['moved'][$fid] = $destination;
          $this->report($progress, 'moved', $fid, $destination);
        }
        catch (\Throwable $e) {
          $results['failed'][$fid] = $e->getMessage();
          $this->logger->error('Could not move file @fid from @uri: @message', [
            '@fid' => $fid,
            '@uri' => $uri,
            '@message' => $e->getMessage(),
          ]);
          $this->report($progress, 'failed', $fid, $e->getMessage());
        }
      }

      $storage->resetCache($chunk);
    }

    return $results;
  }

  /**
   * Finds text fields containing a hard-coded path into the public files dir.
   *
   * Media embeds and entity reference fields resolve through the file entity
   * and follow a move. A path typed or pasted into a text field does not, and
   * will break once the file it points at is private. Those need a human
   * decision, so they are reported rather than rewritten.
   *
   * @return array
   *   A list of arrays with 'table', 'column', 'entity_id' and 'revision_id'
   *   keys, one per row containing a hard-coded path.
   */
  public function findHardCodedPaths(): array {
    $needle = '%' . $this->database->escapeLike('/' . PublicStream::basePath() . '/') . '%';
    $found = [];

    foreach ($this->getScannableTables() as $table => $column) {
      if (!$this->database->schema()->tableExists($table)) {
        continue;
      }

      $query = $this->database->select($table, 't')
        ->fields('t', ['entity_id', 'revision_id'])
        ->condition('t.' . $column, $needle, 'LIKE');

      foreach ($query->execute() as $row) {
        $found[] = [
          'table' => $table,
          'column' => $column,
          'entity_id' => $row->entity_id,
          'revision_id' => $row->revision_id,
        ];
      }
    }

    return $found;
  }

  /**
   * Builds an entity query for managed files on a scheme.
   *
   * @param string $scheme
   *   The scheme to match, without the '://' suffix.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   The prepared query.
   */
  protected function fileQuery(string $scheme) {
    return $this->entityTypeManager->getStorage('file')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('uri', $scheme . '://%', 'LIKE');
  }

  /**
   * Maps the field data tables that can hold a hard-coded path to their column.
   *
   * Base fields live on the entity's own table rather than a dedicated field
   * table, so only configurable fields are covered here. That is where pasted
   * content ends up.
   *
   * @return array
   *   Table name keyed to the column holding the field's value.
   */
  protected function getScannableTables(): array {
    $tables = [];

    foreach (static::SCANNED_FIELD_TYPES as $type => $column) {
      foreach ($this->entityFieldManager->getFieldMapByFieldType($type) as $entity_type_id => $fields) {
        foreach (array_keys($fields) as $field_name) {
          foreach (["{$entity_type_id}__{$field_name}", "{$entity_type_id}_revision__{$field_name}"] as $table) {
            $tables[$table] = $field_name . '_' . $column;
          }
        }
      }
    }

    return $tables;
  }

  /**
   * Invokes the progress callback if one was supplied.
   *
   * @param callable|null $progress
   *   The callback, or NULL.
   * @param string $outcome
   *   One of 'moved', 'missing' or 'failed'.
   * @param int $fid
   *   The file ID.
   * @param string $detail
   *   The destination URI or failure reason.
   */
  protected function report(?callable $progress, string $outcome, int $fid, string $detail): void {
    if ($progress) {
      $progress($outcome, $fid, $detail);
    }
  }

}
