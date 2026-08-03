<?php

namespace Drupal\uiowa_core\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\uiowa_core\FileSchemeMigrator;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for moving managed files between stream wrappers.
 */
class FileSchemeCommands extends DrushCommands {

  /**
   * Maps a destination scheme to the scheme files are taken from.
   */
  const DIRECTIONS = [
    'private' => 'public',
    'public' => 'private',
  ];

  /**
   * Command constructor.
   */
  public function __construct(
    protected FileSchemeMigrator $migrator,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct();
  }

  /**
   * Moves managed files between the public and private file systems.
   *
   * Changing a field's uri_scheme only affects new uploads. Existing files keep
   * their original URI, so this command moves them to match.
   *
   * @param string $to
   *   The destination scheme, either 'private' or 'public'.
   * @param array $options
   *   Additional options for the command.
   *
   * @command uiowa_core:migrate-files
   * @aliases uicore-mf
   *
   * @option dry-run Report what would move without moving anything.
   * @option skip-flush Leave image style derivatives in place.
   *
   * @usage uiowa_core:migrate-files private --dry-run
   *   List the files that would move to private storage.
   * @usage uiowa_core:migrate-files private
   *   Move every public file into private storage.
   * @usage uiowa_core:migrate-files public
   *   Move every private file back into public storage.
   */
  public function migrateFiles(string $to, array $options = ['dry-run' => FALSE, 'skip-flush' => FALSE]) {
    if (!isset(static::DIRECTIONS[$to])) {
      $this->logger()->error(dt("Destination must be 'private' or 'public', got '@to'.", ['@to' => $to]));
      return self::EXIT_FAILURE;
    }

    $from = static::DIRECTIONS[$to];
    $dry_run = (bool) $options['dry-run'];

    if ($reason = $this->migrator->checkScheme($to)) {
      $this->logger()->error(dt('Cannot write to @to://: @reason', [
        '@to' => $to,
        '@reason' => $reason,
      ]));
      return self::EXIT_FAILURE;
    }

    $total = $this->migrator->countFiles($from);

    if ($total === 0) {
      $this->logger()->success(dt('No files in @from:// to move.', ['@from' => $from]));
      return self::EXIT_SUCCESS;
    }

    $this->warnAboutHardCodedPaths();

    $this->io()->text(dt('@count file(s) in @from:// will move to @to://.', [
      '@count' => $total,
      '@from' => $from,
      '@to' => $to,
    ]));

    if ($excluded = $this->migrator->countExcluded($from)) {
      $this->io()->text(dt('@count file(s) left in @from:// by an exclusion (@dirs).', [
        '@count' => $excluded,
        '@from' => $from,
        '@dirs' => implode(', ', FileSchemeMigrator::EXCLUDED_DIRECTORIES),
      ]));
    }

    if (!$dry_run && !$this->io()->confirm(dt('Move them?'), FALSE)) {
      return self::EXIT_FAILURE;
    }

    $results = $this->runMigration($from, $to, $dry_run, $total);

    if ($dry_run) {
      $this->logger()->success(dt('Dry run: @count file(s) would move. Nothing was changed.', [
        '@count' => count($results['moved']),
      ]));
      return self::EXIT_SUCCESS;
    }

    if ($results['moved'] && !$options['skip-flush']) {
      $this->flushImageStyles();
    }

    return $this->summarize($results);
  }

  /**
   * Runs the migration with a progress bar.
   *
   * @param string $from
   *   The source scheme.
   * @param string $to
   *   The destination scheme.
   * @param bool $dry_run
   *   Whether this is a dry run.
   * @param int $total
   *   The number of files to process, for the progress bar.
   *
   * @return array
   *   The migration results.
   */
  protected function runMigration(string $from, string $to, bool $dry_run, int $total): array {
    $this->io()->progressStart($total);

    $results = $this->migrator->migrate($from, $to, $dry_run, function (string $outcome, int $fid, string $detail) {
      $this->io()->progressAdvance();

      if ($outcome !== 'moved') {
        $this->logger()->warning(dt('@outcome: file @fid — @detail', [
          '@outcome' => $outcome,
          '@fid' => $fid,
          '@detail' => $detail,
        ]));
      }
    });

    $this->io()->progressFinish();

    return $results;
  }

  /**
   * Warns about hard-coded public file paths in text fields.
   *
   * These do not follow a move and will break. Rewriting them is a content
   * decision, so the command reports and leaves them alone.
   */
  protected function warnAboutHardCodedPaths(): void {
    $found = $this->migrator->findHardCodedPaths();

    if (!$found) {
      return;
    }

    $this->logger()->warning(dt('@count hard-coded path(s) into the public files directory found. These will not follow the move and will break. Review them before continuing.', [
      '@count' => count($found),
    ]));

    $this->io()->table(
      ['Source', 'Location', 'Detail'],
      array_map('array_values', $found)
    );
  }

  /**
   * Deletes image style derivatives so they regenerate from the new location.
   *
   * Derivatives built from a public source stay readable by URL even after the
   * source moves, so flushing them is part of the move, not a nicety.
   */
  protected function flushImageStyles(): void {
    $styles = $this->entityTypeManager->getStorage('image_style')->loadMultiple();

    foreach ($styles as $style) {
      $style->flush();
    }

    $this->logger()->success(dt('Flushed derivatives for @count image style(s).', [
      '@count' => count($styles),
    ]));
  }

  /**
   * Reports the outcome and returns the command exit code.
   *
   * @param array $results
   *   The migration results.
   *
   * @return int
   *   The exit code.
   */
  protected function summarize(array $results): int {
    $this->logger()->success(dt('Moved @count file(s).', ['@count' => count($results['moved'])]));

    foreach (['missing' => 'had no file on disk', 'failed' => 'could not be moved'] as $key => $description) {
      if ($results[$key]) {
        $this->logger()->warning(dt('@count file(s) @description: @fids', [
          '@count' => count($results[$key]),
          '@description' => $description,
          '@fids' => implode(', ', array_keys($results[$key])),
        ]));
      }
    }

    return $results['failed'] ? self::EXIT_FAILURE : self::EXIT_SUCCESS;
  }

}
