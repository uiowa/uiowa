<?php

namespace Drupal\sitenow_migrate\Plugin\migrate\source;

use Drupal\Component\Utility\Html;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\State\StateInterface;
use Drupal\migrate\Event\ImportAwareInterface;
use Drupal\migrate\Event\MigrateImportEvent;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Drupal\node\Plugin\migrate\source\d7\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides base node source abstract class with additional functionality.
 *
 * @see \Drupal\node\Plugin\migrate\source\d7\Node
 */
abstract class BaseNodeSource extends Node implements ImportAwareInterface {
  use LoggerChannelTrait;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The sitenow_migrate logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Number of records to fetch from the database during each batch.
   *
   * A value of zero indicates no batching is to be done.
   *
   * @var int
   */
  protected $batchSize = 100;

  /**
   * Counter for memory resets.
   *
   * @var int
   */
  protected $rowCount = 0;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, StateInterface $state, ModuleHandlerInterface $module_handler, FileSystemInterface $file_system, EntityTypeManager $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration, $state, $entityTypeManager, $module_handler);
    $this->fileSystem = $file_system;
    $this->logger = $this->getLogger('sitenow_migrate');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('state'),
      $container->get('module_handler'),
      $container->get('file_system'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    parent::prepareRow($row);
    $moderation_state = $row->getSourceProperty('status') == 1 ? 'published' : 'draft';
    $row->setSourceProperty('moderation_state', $moderation_state);
  }

  /**
   * Extract a plain text summary from a block of text.
   *
   * @todo Use smart_trim for this.
   *
   * @param string $text
   *   The text to convert to a trimmed plain text version.
   *
   * @return string
   *   The plain text string.
   */
  protected function extractSummaryFromText(string $text) {
    // Strip out any HTML, decode special characters and replace spaces.
    $new_summary = Html::decodeEntities($text);
    $new_summary = str_replace('&nbsp;', ' ', $new_summary);
    // Also want to remove any excess whitespace on the left
    // that might cause weird spacing for our summaries.
    $new_summary = ltrim(strip_tags($new_summary));

    $new_summary = substr($new_summary, 0, 200);

    $looper = TRUE;

    // Shorten the string until we reach a natural(ish) breaking point.
    while ($looper && strlen($new_summary) > 0) {
      switch (substr($new_summary, -1)) {

        case '.':
        case '!':
        case '?':
          $looper = FALSE;
          break;

        case ';':
        case ':':
        case '"':
          $looper = FALSE;
          $new_summary = $new_summary . '...';
          break;

        default:
          $new_summary = substr($new_summary, 0, -1);
      }
    }

    return $new_summary;
  }

  /**
   * Fetch additional multi-value fields from our database.
   *
   * @param \Drupal\migrate\Row $row
   *   The migration row result.
   * @param array $tables
   *   An associative array of table names and fields to add.
   */
  public function fetchAdditionalFields(Row &$row, array $tables) {
    $nid = $row->getSourceProperty('nid');
    foreach ($tables as $table_name => $fields) {
      foreach ($fields as $field) {
        $row->setSourceProperty($field, $this->select($table_name, 't')
          ->fields('t', [$field])
          ->condition('entity_id', $nid, '=')
          ->execute()
          ->fetchCol());
        unset($field);
      }
    }
  }

  /**
   * Fetch url aliases from our database.
   *
   * @param \Drupal\migrate\Row $row
   *   The migration row result.
   */
  public function fetchUrlAliases(Row &$row) {
    $nid = $row->getSourceProperty('nid');
    $row->setSourceProperty('alias', $this->select('url_alias', 'alias')
      ->fields('alias', ['alias'])
      ->condition('source', 'node/' . $nid, '=')
      ->execute()
      ->fetchCol());
  }

  /**
   * Run post-migration tasks.
   *
   * @param \Drupal\migrate\Event\MigrateImportEvent $event
   *   The migrate import event.
   */
  public function postImport(MigrateImportEvent $event) {}

  /**
   * Run pre-migration tasks.
   *
   * @param \Drupal\migrate\Event\MigrateImportEvent $event
   *   The migrate import event.
   */
  public function preImport(MigrateImportEvent $event) {}

  /**
   * Attempt to clear the entity cache if needed to avoid memory overflows.
   *
   * This method should be called in migration source prepareRow methods.
   *
   * @param int $size
   *   The number of rows to reset memory after.
   *
   * @see MigrateExecutable::attemptMemoryReclaim
   *
   * @return int
   *   Return the existing memory usage.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function clearMemory($size = 100) {
    if ($this->rowCount++ % $size == 0) {
      // First, try resetting Drupal's static storage - this frequently releases
      // plenty of memory to continue.
      drupal_static_reset();

      // Entity storage can blow up with caches so clear them out.
      \Drupal::service('entity.memory_cache')->deleteAll();

      // Run garbage collector to further reduce memory.
      gc_collect_cycles();
    }
    return memory_get_usage();
  }

  /**
   * Return the summary of a text field.
   *
   * @param array $field
   *   A text field array that includes value, format and summary keys.
   *
   * @return string
   *   The summary if set or an extraction of the body value if not.
   */
  public function getSummaryFromTextField(array $field): string {
    if (empty($field[0]['summary'])) {
      return $this->extractSummaryFromText($field[0]['value']);
    }
    else {
      return $field[0]['summary'];
    }
  }

  /**
   * Return the nid of the last-most migrated node.
   *
   * @param string $migration_id
   *   The migration id, used to construct the migrate_map_ table name.
   *
   * @return int
   *   The node id of the last-most migrated node.
   */
  public function getLastMigrated(string $migration_id) {
    $last_migrated_query = \Drupal::database()->select('migrate_map_' . $migration_id, 'm')
      ->fields('m', ['sourceid1'])
      ->orderBy('sourceid1', 'DESC');
    return $last_migrated_query->execute()->fetch()->sourceid1;
  }

}
