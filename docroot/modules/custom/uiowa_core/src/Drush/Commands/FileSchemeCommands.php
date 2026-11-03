<?php

namespace Drupal\uiowa_core\Drush\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\uiowa_core\FileSchemeMigrator;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Drush\Exceptions\UserAbortException;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * Builds the command handler from the container.
   *
   * Drush instantiates attribute-discovered command classes inside a
   * try/catch, so a container cached before this module's services existed
   * drops the command instead of fatalling every drush call on the site. A
   * drush.services.yml entry has no such protection.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The Drupal service container.
   *
   * @return self
   *   The command handler.
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('uiowa_core.file_scheme_migrator'),
      $container->get('entity_type.manager'),
    );
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
   */
  #[CLI\Command(name: 'uiowa_core:migrate-files', aliases: ['uicore-mf'])]
  #[CLI\Argument(name: 'to', description: "The destination scheme, either 'private' or 'public'.")]
  #[CLI\Option(name: 'dry-run', description: 'Report what would move without moving anything.')]
  #[CLI\Option(name: 'skip-flush', description: 'Leave image style derivatives in place.')]
  #[CLI\Option(name: 'no-rewrite', description: 'Leave content references pointing at the old location.')]
  #[CLI\Option(name: 'list-paths', description: 'List every hard-coded path instead of counting them.')]
  #[CLI\Usage(name: 'uiowa_core:migrate-files private --dry-run', description: 'List the files that would move to private storage.')]
  #[CLI\Usage(name: 'uiowa_core:migrate-files private --dry-run --list-paths', description: 'Also list every content reference that would be rewritten.')]
  #[CLI\Usage(name: 'uiowa_core:migrate-files private', description: 'Move every public file into private storage.')]
  #[CLI\Usage(name: 'uiowa_core:migrate-files public', description: 'Move every private file back into public storage.')]
  public function migrateFiles(
    string $to,
    array $options = [
      'dry-run' => FALSE,
      'skip-flush' => FALSE,
      'no-rewrite' => FALSE,
      'list-paths' => FALSE,
    ],
  ) {
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

    $this->reportHardCodedPaths((bool) $options['list-paths'], $from);

    $this->io()->text(dt('@count file(s) in @from:// will move to @to://.', [
      '@count' => $total,
      '@from' => $from,
      '@to' => $to,
    ]));

    // Declining the prompt is a decision, not a failure. Drush core signals
    // that with UserAbortException rather than an error exit code.
    if (!$dry_run && !$this->io()->confirm(dt('Move them?'), FALSE)) {
      throw new UserAbortException();
    }

    $results = $this->runMigration($from, $to, $dry_run, $total);

    $rewritten = [];

    if ($results['moved'] && !$options['no-rewrite']) {
      $rewritten = $this->migrator->rewriteReferences($results['moved'], $from, $to, $dry_run);
      $this->reportRewrites($rewritten, $dry_run);
    }

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
   * Reports hard-coded source-scheme file paths found in content.
   *
   * Shown before the move so the operator sees the scope up front. Those
   * pointing at files being moved are rewritten afterwards; the rest are
   * pre-existing broken links and are left as they are.
   *
   * @param bool $list
   *   List every occurrence instead of counting by location.
   * @param string $from
   *   The scheme files are moving out of, whose paths are the ones at risk.
   */
  protected function reportHardCodedPaths(bool $list, string $from): void {
    $found = $this->migrator->findHardCodedPaths($from);

    if (!$found) {
      return;
    }

    $this->logger()->notice(dt('@count hard-coded path(s) into the @from files directory found. Those pointing at files being moved are rewritten; any pointing elsewhere are left alone and listed below.', [
      '@count' => count($found),
      '@from' => $from,
    ]));

    if ($list) {
      $this->io()->table(
        ['Source', 'Location', 'Detail'],
        array_map('array_values', $found)
      );
      return;
    }

    $counts = [];

    foreach ($found as $item) {
      $key = $item['source'] . ' | ' . $item['location'];
      $counts[$key] = ($counts[$key] ?? 0) + 1;
    }

    arsort($counts);

    $this->io()->table(
      ['Source and location', 'Rows'],
      array_map(NULL, array_keys($counts), array_values($counts))
    );
    $this->io()->text(dt('Run again with --list-paths for the full list.'));
  }

  /**
   * Reports what the rewrite changed, grouped by location.
   *
   * @param array $rewritten
   *   The result of the rewrite.
   * @param bool $dry_run
   *   Whether this was a dry run.
   */
  protected function reportRewrites(array $rewritten, bool $dry_run): void {
    $rows = $rewritten['rows'] ?? 0;

    if ($rows === 0) {
      $this->logger()->notice(dt('No content references needed rewriting.'));
      return;
    }

    unset($rewritten['rows']);
    arsort($rewritten);

    $this->io()->table(
      [$dry_run ? 'Would rewrite' : 'Rewrote', 'Rows'],
      array_map(NULL, array_keys($rewritten), array_values($rewritten))
    );

    $this->logger()->success(dt('@verb @count content reference row(s).', [
      '@verb' => $dry_run ? 'Would rewrite' : 'Rewrote',
      '@count' => $rows,
    ]));
  }

  /**
   * Deletes image style derivatives so they regenerate from the new location.
   *
   * Derivatives built from a public source stay readable by URL even after the
   * source moves, so flushing them is part of the move, not a nicety.
   */
  protected function flushImageStyles(): void {
    if (!$this->entityTypeManager->hasDefinition('image_style')) {
      return;
    }

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
