<?php

namespace Drupal\uiowa_core;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\DefaultTableMapping;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Database\Connection;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StreamWrapper\PublicStream;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\file\FileInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\layout_builder\SectionStorageInterface;
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
   * Matches the tempstore collections holding unsaved layout builder edits.
   *
   * Layout builder keeps one collection per section storage type, currently
   * 'overrides' and 'defaults'. Matching on the shared prefix keeps both in
   * scope, and keeps the scan and the rewrite looking at the same rows.
   */
  const LAYOUT_TEMPSTORE_PATTERN = 'tempstore.shared.layout_builder.section_storage.%';

  /**
   * Field types that can hold a hard-coded file path, keyed to their columns.
   *
   * Each column is a property of the field, and a type can store a path in
   * more than one of them: a teaser written by hand goes in the summary rather
   * than the value. Layout builder sections are handled separately because
   * their configuration is serialized rather than stored as a plain value.
   */
  const SCANNED_FIELD_TYPES = [
    'text' => ['value'],
    'text_long' => ['value'],
    'text_with_summary' => ['value', 'summary'],
    'string_long' => ['value'],
    'link' => ['uri'],
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
   * Finds hard-coded paths into the public files directory.
   *
   * Media embeds and entity reference fields resolve through the file entity
   * and follow a move. A path typed or pasted into content does not, and will
   * break once the file it points at is private. Those need a human decision,
   * so they are reported rather than rewritten.
   *
   * Three places have to be checked. Configurable text and link fields cover
   * ordinary content. Layout builder sections hold block configuration in a
   * serialized blob, so a path there is invisible to a field scan. The layout
   * builder tempstore holds unsaved edits, including inline blocks that exist
   * nowhere else yet, and those will break as soon as an editor saves.
   *
   * @return array
   *   A list of arrays with 'source', 'location' and 'detail' keys.
   */
  public function findHardCodedPaths(string $scheme = 'public'): array {
    $path = $this->urlPath($scheme, '');

    return array_merge(
      $this->scanFieldTables($path),
      $this->scanLayoutSections($path),
      $this->scanLayoutTempstore($path),
    );
  }

  /**
   * Scans configurable text and link field tables for a path.
   *
   * @param string $path
   *   The public files path to look for.
   *
   * @return array
   *   Findings keyed 'source', 'location' and 'detail'.
   */
  protected function scanFieldTables(string $path): array {
    $found = [];

    foreach ($this->getScannableTables() as $table => $columns) {
      if (!$this->database->schema()->tableExists($table)) {
        continue;
      }

      foreach ($columns as $column) {
        $query = $this->database->select($table, 't')
          ->fields('t', ['entity_id', 'revision_id'])
          ->condition('t.' . $column, '%' . $this->database->escapeLike($path) . '%', 'LIKE');

        foreach ($query->execute() as $row) {
          $found[] = [
            'source' => 'field',
            'location' => $table . '.' . $column,
            'detail' => sprintf('entity %s, revision %s', $row->entity_id, $row->revision_id),
          ];
        }
      }
    }

    return $found;
  }

  /**
   * Scans saved layout builder sections for a path.
   *
   * The LIKE narrows the rows worth deserializing; the component walk is what
   * turns a matching row into something a human can go and fix.
   *
   * @param string $path
   *   The public files path to look for.
   *
   * @return array
   *   Findings keyed 'source', 'location' and 'detail'.
   */
  protected function scanLayoutSections(string $path): array {
    $found = [];

    foreach ($this->getLayoutTables() as $table => $column) {
      if (!$this->database->schema()->tableExists($table)) {
        continue;
      }

      $query = $this->database->select($table, 't')
        ->fields('t', ['entity_id', 'revision_id', 'delta', $column])
        ->condition('t.' . $column, '%' . $this->database->escapeLike($path) . '%', 'LIKE');

      foreach ($query->execute() as $row) {
        $where = sprintf('entity %s, revision %s, section %s', $row->entity_id, $row->revision_id, $row->delta);
        $section = unserialize($row->{$column}, [
          'allowed_classes' => [Section::class, SectionComponent::class],
        ]);

        // The LIKE already proved the path is in this row. If the blob is not
        // the Section we expect, say so rather than reporting nothing.
        if (!$section instanceof Section) {
          $found[] = [
            'source' => 'layout',
            'location' => $table,
            'detail' => $where . ', could not read section, inspect manually',
          ];
          continue;
        }

        foreach ($this->matchingComponents($section, $path) as $description) {
          $found[] = [
            'source' => 'layout',
            'location' => $table,
            'detail' => $where . ', ' . $description,
          ];
        }
      }
    }

    return $found;
  }

  /**
   * Scans the layout builder tempstore for a path in unsaved edits.
   *
   * @param string $path
   *   The public files path to look for.
   *
   * @return array
   *   Findings keyed 'source', 'location' and 'detail'.
   */
  protected function scanLayoutTempstore(string $path): array {
    if (!$this->database->schema()->tableExists('key_value_expire')) {
      return [];
    }

    $found = [];

    // A tempstore blob wraps an arbitrary object graph, including inline block
    // entities carried as 'block_serialized'. Restricting allowed classes well
    // enough to deserialize it safely is not practical, and guessing wrong
    // would mean silently reporting nothing, so the row itself is the finding.
    $query = $this->database->select('key_value_expire', 'kve')
      ->fields('kve', ['collection', 'name'])
      ->condition('collection', static::LAYOUT_TEMPSTORE_PATTERN, 'LIKE')
      ->condition('value', '%' . $this->database->escapeLike($path) . '%', 'LIKE');

    foreach ($query->execute() as $row) {
      $found[] = [
        'source' => 'tempstore',
        'location' => 'key_value_expire',
        'detail' => sprintf('unsaved layout edit for %s in %s', $row->name, $row->collection),
      ];
    }

    return $found;
  }

  /**
   * Describes the components of a section whose configuration holds a path.
   *
   * @param \Drupal\layout_builder\Section $section
   *   The section to walk.
   * @param string $path
   *   The public files path to look for.
   *
   * @return string[]
   *   An operator-facing description per matching component.
   */
  protected function matchingComponents(Section $section, string $path): array {
    $descriptions = [];

    foreach ($section->getComponents() as $component) {
      $configuration = $component->toArray()['configuration'] ?? [];

      if (!$this->containsPath($configuration, $path)) {
        continue;
      }

      $revision_id = $configuration['block_revision_id'] ?? NULL;

      $descriptions[] = $revision_id
        ? sprintf('block %s (revision %s)', $component->getPluginId(), $revision_id)
        : sprintf('block %s (unsaved)', $component->getPluginId());
    }

    return $descriptions;
  }

  /**
   * Checks whether any string nested in a value contains a path.
   *
   * Block configuration nests arbitrarily, and an inline block may be carried
   * as a serialized string, so both need looking through.
   *
   * @param mixed $value
   *   The value to search.
   * @param string $path
   *   The public files path to look for.
   *
   * @return bool
   *   TRUE if the path appears anywhere in the value.
   */
  protected function containsPath(mixed $value, string $path): bool {
    if (is_string($value)) {
      return str_contains($value, $path);
    }

    if (is_array($value)) {
      foreach ($value as $item) {
        if ($this->containsPath($item, $path)) {
          return TRUE;
        }
      }
    }

    return FALSE;
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
   *   Table name keyed to the list of columns holding the field's values.
   */
  protected function getScannableTables(): array {
    $tables = [];

    foreach (static::SCANNED_FIELD_TYPES as $type => $columns) {
      foreach ($this->entityFieldManager->getFieldMapByFieldType($type) as $entity_type_id => $fields) {
        $mapping = $this->getTableMapping($entity_type_id);

        if (!$mapping) {
          continue;
        }

        $definitions = $this->entityFieldManager->getFieldStorageDefinitions($entity_type_id);

        foreach (array_keys($fields) as $field_name) {
          if (!isset($definitions[$field_name])) {
            continue;
          }

          $definition = $definitions[$field_name];

          // Base fields share the entity's own table, which this scan does not
          // cover.
          if (!$mapping->requiresDedicatedTableStorage($definition)) {
            continue;
          }

          // A field table is not always the entity type and field name joined.
          // Core truncates anything over 48 characters and substitutes a hash,
          // so the name has to be asked for rather than reconstructed. The
          // revision tables are the ones that overflow in practice, and they
          // are what layout builder renders inline blocks from.
          $names = array_map(
            fn (string $column) => $mapping->getFieldColumnName($definition, $column),
            $columns
          );

          $tables[$mapping->getDedicatedDataTableName($definition)] = $names;
          $tables[$mapping->getDedicatedRevisionTableName($definition)] = $names;
        }
      }
    }

    return $tables;
  }

  /**
   * Maps the layout builder section tables to their column.
   *
   * Layout sections are serialized rather than stored as plain values, so they
   * are scanned and rewritten separately from the other field tables, but the
   * table names come from the same place.
   *
   * @return array
   *   Table name keyed to the column holding the serialized section.
   */
  protected function getLayoutTables(): array {
    $tables = [];

    foreach ($this->entityFieldManager->getFieldMapByFieldType('layout_section') as $entity_type_id => $fields) {
      $mapping = $this->getTableMapping($entity_type_id);

      if (!$mapping) {
        continue;
      }

      $definitions = $this->entityFieldManager->getFieldStorageDefinitions($entity_type_id);

      foreach (array_keys($fields) as $field_name) {
        if (!isset($definitions[$field_name])) {
          continue;
        }

        $definition = $definitions[$field_name];

        if (!$mapping->requiresDedicatedTableStorage($definition)) {
          continue;
        }

        $column = $mapping->getFieldColumnName($definition, 'section');

        $tables[$mapping->getDedicatedDataTableName($definition)] = $column;
        $tables[$mapping->getDedicatedRevisionTableName($definition)] = $column;
      }
    }

    return $tables;
  }

  /**
   * Returns the SQL table mapping for an entity type, if it has one.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return \Drupal\Core\Entity\Sql\DefaultTableMapping|null
   *   The mapping, or NULL if the entity type is not stored in SQL tables.
   */
  protected function getTableMapping(string $entity_type_id): ?DefaultTableMapping {
    try {
      $storage = $this->entityTypeManager->getStorage($entity_type_id);
    }
    catch (PluginNotFoundException) {
      return NULL;
    }

    if (!$storage instanceof SqlContentEntityStorage) {
      return NULL;
    }

    $mapping = $storage->getTableMapping();

    return $mapping instanceof DefaultTableMapping ? $mapping : NULL;
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

  /**
   * Rewrites content references to files that have moved.
   *
   * Only references to files this migration actually moved are rewritten. A
   * path pointing at something else, typically a file deleted long ago that
   * already serves a 404, is left exactly as it is: rewriting it would turn one
   * broken link into a different broken link and hide the real problem.
   *
   * @param string[] $destinations
   *   The destination URIs of the files that moved, as returned in the 'moved'
   *   key of ::migrate(). Destinations are used rather than file IDs so that a
   *   dry run, where nothing has actually moved yet, maps the same paths a real
   *   run would.
   * @param string $from
   *   The scheme the files were moved out of.
   * @param string $to
   *   The scheme the files were moved into.
   * @param bool $dry_run
   *   Count what would change without writing.
   *
   * @return array
   *   Occurrence counts keyed by location, plus a 'rows' total.
   */
  public function rewriteReferences(array $destinations, string $from, string $to, bool $dry_run = FALSE): array {
    $map = [];
    $targets = [];

    foreach ($destinations as $uri) {
      $target = $this->streamWrapperManager->getTarget($uri);

      foreach ($this->pathVariants($target) as $variant) {
        $map[$this->urlPath($from, $variant)] = $this->urlPath($to, $variant);
        $targets[$variant] = TRUE;
      }
    }

    if (!$map) {
      return ['rows' => 0];
    }

    // Everything the rewrite needs, resolved once. Passing it down keeps the
    // string rewriting free of global state and testable on its own.
    $context = [
      'map' => $map,
      'targets' => $targets,
      'from' => $from,
      'to' => $to,
      'search' => $this->urlPath($from, ''),
      'styles_from' => $this->urlPath($from, 'styles/'),
      'styles_to' => $this->urlPath($to, 'styles/'),
    ];

    $results = ['rows' => 0];

    foreach ([
      $this->rewriteFieldTables($context, $dry_run),
      $this->rewriteLayoutSections($context, $dry_run),
      $this->rewriteTempstore($context, $dry_run),
    ] as $pass) {
      foreach ($pass as $location => $count) {
        $results[$location] = ($results[$location] ?? 0) + $count;
        $results['rows'] += $count;
      }
    }

    return $results;
  }

  /**
   * Builds the raw and percent-encoded forms of a file target.
   *
   * Content holds whichever form the editor pasted, and Drupal generates the
   * encoded one, so both have to be matched.
   *
   * @param string $target
   *   The path within the stream wrapper.
   *
   * @return string[]
   *   The distinct variants to match.
   */
  protected function pathVariants(string $target): array {
    $encoded = implode('/', array_map('rawurlencode', explode('/', $target)));

    return $encoded === $target ? [$target] : [$target, $encoded];
  }

  /**
   * Builds the URL path a scheme serves a target at.
   *
   * @param string $scheme
   *   Either 'public' or 'private'.
   * @param string $target
   *   The path within the stream wrapper.
   *
   * @return string
   *   The URL path, without scheme or host.
   */
  protected function urlPath(string $scheme, string $target): string {
    return $scheme === 'private'
      ? '/system/files/' . $target
      : '/' . PublicStream::basePath() . '/' . $target;
  }

  /**
   * Rewrites a string, including any image style derivative paths in it.
   *
   * @param string $value
   *   The value to rewrite.
   * @param array $context
   *   The rewrite context built by ::rewriteReferences().
   *
   * @return string
   *   The rewritten value.
   */
  protected function rewriteString(string $value, array $context): string {
    // Derivatives carry the source scheme as a path segment of their own, so
    // they need handling before the direct paths are swapped out from under
    // them. Only a derivative whose source actually moved is rewritten: one
    // built from a file left behind still resolves where it is, and pointing
    // it at the destination scheme would break an image that works today.
    if (str_contains($value, $context['styles_from'])) {
      $pattern = '#'
        . preg_quote($context['styles_from'], '#')
        . '([^/]+)/'
        . preg_quote($context['from'], '#')
        . '/([^"\'\s<>?]+)#';

      $value = preg_replace_callback($pattern, function (array $matches) use ($context) {
        if (!isset($context['targets'][$matches[2]])) {
          return $matches[0];
        }

        return $context['styles_to'] . $matches[1] . '/' . $context['to'] . '/' . $matches[2];
      }, $value);
    }

    return strtr($value, $context['map']);
  }

  /**
   * Rewrites every scannable field table.
   *
   * @param array $context
   *   The rewrite context built by ::rewriteReferences().
   * @param bool $dry_run
   *   Count without writing.
   *
   * @return array
   *   Changed row counts keyed by table and column.
   */
  protected function rewriteFieldTables(array $context, bool $dry_run): array {
    $changed = [];

    foreach ($this->getScannableTables() as $table => $columns) {
      if (!$this->database->schema()->tableExists($table)) {
        continue;
      }

      foreach ($columns as $column) {
        $rows = $this->database->select($table, 't')
          ->fields('t', ['entity_id', 'revision_id', 'langcode', 'delta', $column])
          ->condition('t.' . $column, '%' . $this->database->escapeLike($context['search']) . '%', 'LIKE')
          ->execute();

        foreach ($rows as $row) {
          $rewritten = $this->rewriteString((string) $row->{$column}, $context);

          if ($rewritten === $row->{$column}) {
            continue;
          }

          $changed[$table . '.' . $column] = ($changed[$table . '.' . $column] ?? 0) + 1;

          if (!$dry_run) {
            $this->database->update($table)
              ->fields([$column => $rewritten])
              ->condition('revision_id', $row->revision_id)
              ->condition('langcode', $row->langcode)
              ->condition('delta', $row->delta)
              ->execute();
          }
        }
      }
    }

    return $changed;
  }

  /**
   * Rewrites saved layout builder sections.
   *
   * @param array $context
   *   The rewrite context built by ::rewriteReferences().
   * @param bool $dry_run
   *   Count without writing.
   *
   * @return array
   *   Changed row counts keyed by table.
   */
  protected function rewriteLayoutSections(array $context, bool $dry_run): array {
    $changed = [];

    foreach ($this->getLayoutTables() as $table => $column) {
      if (!$this->database->schema()->tableExists($table)) {
        continue;
      }

      $rows = $this->database->select($table, 't')
        ->fields('t', ['revision_id', 'langcode', 'delta', $column])
        ->condition('t.' . $column, '%' . $this->database->escapeLike($context['search']) . '%', 'LIKE')
        ->execute();

      foreach ($rows as $row) {
        $section = unserialize($row->{$column}, [
          'allowed_classes' => [Section::class, SectionComponent::class],
        ]);

        if (!$section instanceof Section) {
          $this->logger->warning('Skipped a layout section in @table revision @id: the stored value is not a Section.', [
            '@table' => $table,
            '@id' => $row->revision_id,
          ]);
          continue;
        }

        if (!$this->rewriteSection($section, $context)) {
          continue;
        }

        $changed[$table] = ($changed[$table] ?? 0) + 1;

        if (!$dry_run) {
          // Translations get a row each, so langcode is part of the key.
          // Without it one language's layout overwrites all of them.
          $this->database->update($table)
            ->fields([$column => serialize($section)])
            ->condition('revision_id', $row->revision_id)
            ->condition('langcode', $row->langcode)
            ->condition('delta', $row->delta)
            ->execute();
        }
      }
    }

    return $changed;
  }

  /**
   * Rewrites unsaved layout edits held in the tempstore.
   *
   * The stored graph reaches the host entity, so its classes cannot be listed
   * up front. They are read out of the serialized string instead and checked
   * before anything is deserialized, and a lossless round trip is proven on the
   * untouched value before the rewritten one is written back. Any row failing a
   * check is reported and left alone.
   *
   * @param array $context
   *   The rewrite context built by ::rewriteReferences().
   * @param bool $dry_run
   *   Count without writing.
   *
   * @return array
   *   Changed row counts keyed by location.
   */
  protected function rewriteTempstore(array $context, bool $dry_run): array {
    if (!$this->database->schema()->tableExists('key_value_expire')) {
      return [];
    }

    $changed = [];

    $rows = $this->database->select('key_value_expire', 'kve')
      ->fields('kve', ['collection', 'name', 'value'])
      ->condition('collection', static::LAYOUT_TEMPSTORE_PATTERN, 'LIKE')
      ->condition('value', '%' . $this->database->escapeLike($context['search']) . '%', 'LIKE')
      ->execute();

    foreach ($rows as $row) {
      $stored = $this->readTempstoreValue($row->value, $row->name);

      if ($stored === NULL) {
        continue;
      }

      $storage = $stored->data['section_storage'] ?? NULL;

      if (!$storage instanceof SectionStorageInterface) {
        $this->logger->warning('Skipped unsaved layout edit @name: no section storage in the stored value.', [
          '@name' => $row->name,
        ]);
        continue;
      }

      $touched = FALSE;

      foreach ($storage->getSections() as $section) {
        $touched = $this->rewriteSection($section, $context) || $touched;
      }

      if (!$touched) {
        continue;
      }

      $changed['key_value_expire'] = ($changed['key_value_expire'] ?? 0) + 1;

      if (!$dry_run) {
        $this->database->update('key_value_expire')
          ->fields(['value' => serialize($stored)])
          ->condition('collection', $row->collection)
          ->condition('name', $row->name)
          ->execute();
      }
    }

    return $changed;
  }

  /**
   * Deserializes a tempstore value after checking what it contains.
   *
   * @param string $value
   *   The serialized value.
   * @param string $name
   *   The tempstore key, for reporting.
   *
   * @return object|null
   *   The stored object, or NULL if it did not pass a check.
   */
  protected function readTempstoreValue(string $value, string $name): ?object {
    preg_match_all('/O:\d+:"([^"]+)"/', $value, $matches);
    $classes = array_values(array_unique($matches[1]));

    $unknown = array_filter($classes, fn(string $class) => $class !== 'stdClass' && !class_exists($class));

    if ($unknown) {
      $this->logger->warning('Skipped unsaved layout edit @name: it names classes that do not exist here (@classes).', [
        '@name' => $name,
        '@classes' => implode(', ', $unknown),
      ]);
      return NULL;
    }

    $stored = unserialize($value, ['allowed_classes' => $classes]);

    if (!is_object($stored) || !isset($stored->data)) {
      $this->logger->warning('Skipped unsaved layout edit @name: unexpected shape.', ['@name' => $name]);
      return NULL;
    }

    // If an untouched round trip is not byte for byte identical, writing a
    // rewritten one back would change more than intended.
    if (serialize($stored) !== $value) {
      $this->logger->warning('Skipped unsaved layout edit @name: it does not round trip losslessly.', [
        '@name' => $name,
      ]);
      return NULL;
    }

    return $stored;
  }

  /**
   * Rewrites the component configuration of a section in place.
   *
   * @param \Drupal\layout_builder\Section $section
   *   The section to rewrite.
   * @param array $context
   *   The rewrite context built by ::rewriteReferences().
   *
   * @return bool
   *   TRUE if anything changed.
   */
  protected function rewriteSection(Section $section, array $context): bool {
    $touched = FALSE;

    foreach ($section->getComponents() as $component) {
      $configuration = $component->getConfiguration();
      $rewritten = $this->rewriteRecursive($configuration, $context);

      if ($rewritten !== $configuration) {
        $component->setConfiguration($rewritten);
        $touched = TRUE;
      }
    }

    return $touched;
  }

  /**
   * Rewrites every string nested in a value.
   *
   * @param mixed $value
   *   The value to rewrite.
   * @param array $context
   *   The rewrite context built by ::rewriteReferences().
   *
   * @return mixed
   *   The rewritten value.
   */
  protected function rewriteRecursive(mixed $value, array $context): mixed {
    if (is_string($value)) {
      return $this->rewriteString($value, $context);
    }

    if (is_array($value)) {
      foreach ($value as $key => $item) {
        $value[$key] = $this->rewriteRecursive($item, $context);
      }
    }

    return $value;
  }

}
