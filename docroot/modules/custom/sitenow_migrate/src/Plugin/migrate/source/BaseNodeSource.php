<?php

namespace Drupal\sitenow_migrate\Plugin\migrate\source;

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
use Drupal\smart_trim\Truncate\TruncateHTML;

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
        $field = NULL;
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
   * @param int $length
   *   The desired summary length, if new summaries are to be created.
   *
   * @return string
   *   The summary if set or an extraction of the body value if not.
   */
  public function getSummaryFromTextField(array $field, int $length = 400): string {
    if (empty($field[0]['summary'])) {
      return $this->extractSummaryFromText($field[0]['value'], $length);
    }
    else {
      return $field[0]['summary'];
    }
  }

  /**
   * Extract a plain text summary from a block of text.
   *
   * @param string $output
   *   The text to convert to a trimmed plain text version.
   * @param int $length
   *   The desired summary length.
   *
   * @return string
   *   The plain text string.
   */
  protected function extractSummaryFromText(string $output, int $length = 400) {
    // The following is the processing from
    // Drupal\smart_trim\Plugin\Field\FieldFormatter.
    // Strip caption.
    $output = preg_replace('/<figcaption[^>]*>.*?<\/figcaption>/is', ' ', $output);

    // Strip script.
    $output = preg_replace('/<script[^>]*>.*?<\/script>/is', ' ', $output);

    // Strip style.
    $output = preg_replace('/<style[^>]*>.*?<\/style>/is', ' ', $output);

    // Strip tags.
    $output = strip_tags($output);

    // Strip out line breaks.
    $output = preg_replace('/\n|\r|\t/m', ' ', $output);

    // Strip out non-breaking spaces.
    $output = str_replace('&nbsp;', ' ', $output);
    $output = str_replace("\xc2\xa0", ' ', $output);

    // Strip out extra spaces.
    $output = trim(preg_replace('/\s\s+/', ' ', $output));

    $truncate = new TruncateHTML();

    // Truncate to 400 characters with an ellipses.
    $output = $truncate->truncateChars($output, $length, '...');

    return $output;
  }

  /**
   * Return the nid of the last-most migrated node.
   *
   * @return int
   *   The node id of the last-most migrated node.
   */
  public function getLastMigrated() {
    $db = \Drupal::database();
    if (!$db->schema()->tableExists('migrate_map_' . $this->pluginId)) {
      return 0;
    }
    $last_migrated_query = $db->select('migrate_map_' . $this->pluginId, 'm')
      ->fields('m', ['sourceid1'])
      ->orderBy('sourceid1', 'DESC');
    return $last_migrated_query->execute()->fetch()->sourceid1;
  }

}
