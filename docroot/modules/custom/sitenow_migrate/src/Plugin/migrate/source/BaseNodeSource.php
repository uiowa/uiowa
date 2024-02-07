<?php

namespace Drupal\sitenow_migrate\Plugin\migrate\source;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\State\StateInterface;
use Drupal\migrate\Event\ImportAwareInterface;
use Drupal\migrate\Event\MigrateImportEvent;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Drupal\node\Plugin\migrate\source\d7\Node;
use Drupal\smart_trim\TruncateHTML;
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
   * Holder to pass entity ID around.
   *
   * @var int
   */
  protected $entityId = 0;

  /**
   * Collector for post-migrate reporting.
   *
   * @var array
   */
  protected $reporter = [];

  /**
   * Fields with multiple values that need to be fetched.
   *
   * @var array
   */
  protected $multiValueFields = [];

  /**
   * Files with media fields on the source.
   *
   * @var array
   */
  protected $sourceMediaFields = [];

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, StateInterface $state, ModuleHandlerInterface $module_handler, FileSystemInterface $file_system, EntityTypeManager $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration, $state, $entityTypeManager, $module_handler);
    $this->fileSystem = $file_system;
    $this->logger = $this->logger();
    // Add a 'source_file_path' entry to configuration so that we can use it in
    // process plugins later. This is necessary because querying against the
    // source database in a process plugin is not at all straightforward.
    if (!isset($configuration['constants']['source_file_path'])) {
      $this->configuration['constants']['source_file_path'] = $this->variableGet('file_public_path', NULL);
    }
    // Loop through each entry in the process section of the migration config.
    foreach ($this->migration->getProcess() as $processes) {
      // If processes isn't an array, then the plugin is 'get' and we can bail.
      if (!is_array($processes)) {
        continue;
      }
      foreach ($processes as $process) {
        // Check if we are using a plugin that we need this additional
        // processing for.
        // @todo Handle sub-processes as well.
        if (empty($process['plugin'])
          || empty($process['source'])
          || !in_array($process['plugin'], [
            'create_media_from_file_field',
          ])) {
          continue;
        }
        $this->sourceMediaFields[] = $process['source'];
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();
    $source_config = $this->migration->getSourceConfiguration();
    if (isset($source_config['date_limiter']) && $date_limiter = $source_config['date_limiter']) {
      // Only import news newer than the date limiter provided.
      $query->condition('created', strtotime($date_limiter), '>=');
    }
    // Only add the aliases to the query if we're
    // in the redirect migration, otherwise row counts
    // will be off due to one-to-many mapping of nodes to aliases.
    if ($this->migration->getDestinationConfiguration()['plugin'] === 'entity:redirect') {
      $query->leftJoin('url_alias', 'alias', "alias.source = CONCAT('node/', n.nid)");
      $query->fields('alias', ['alias']);
    }
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = parent::fields();
    if ($this->migration->getDestinationConfiguration()['plugin'] === 'entity:redirect') {
      $fields['alias'] = $this->t('The URL alias for this node.');
    }
    return $fields;
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
    $moderation_state = (int) $row->getSourceProperty('status') === 1 ? 'published' : 'draft';
    $row->setSourceProperty('moderation_state', $moderation_state);
    $this->processMultiValueFields($row);
    $this->processMediaFields($row);
  }

  /**
   * Process multi-value fields from source class or YAML.
   *
   * @param \Drupal\migrate\Row $row
   *   The migration row result.
   */
  protected function processMultiValueFields(Row $row) {
    if (!empty($this->configuration['multi_value_fields'])) {
      foreach ($this->configuration['multi_value_fields'] as $field_name => $fields) {
        if (!isset($this->multiValueFields[$field_name])) {
          $this->multiValueFields[$field_name] = $fields;
        }
      }
    }
    if (!empty($this->multiValueFields)) {
      $this->fetchAdditionalFields($row, $this->multiValueFields);
    }
  }

  /**
   * Add additional properties for media fields.
   *
   * @param \Drupal\migrate\Row $row
   *   The migration row result.
   *
   * @throws \Exception
   */
  public function processMediaFields(Row $row) {
    // Loop through each entry in the process section of the migration config.
    foreach ($this->sourceMediaFields as $field_name) {
      $field = $row->getSourceProperty($field_name);
      // If the field wasn't filled on the source,
      // this will be empty and we can exit early.
      if (empty($field)) {
        return;
      }
      $vid = $row->getSourceProperty('vid');
      $query = $this->select("field_revision_$field_name", 'fr')
        ->condition('fr.revision_id', $vid)
        ->orderBy('fm.fid');

      $query->leftJoin('file_managed', 'fm', "fm.fid = fr.{$field_name}_fid");

      $results = $query
        ->fields('fm', ['fid', 'filename', 'uri'])
        ->execute()
        ->fetchAllAssoc('fid');
      // Check if field is multi-value.
      if (is_array($field[0])) {
        // Loop through each value on the field.
        foreach ($field as &$values) {
          $fid = $values['fid'];
          if (isset($results[$fid])) {
            $values += $results[$fid];
          }
        }
      }
      $row->setSourceProperty($field_name, $field);
    }

    // Memory clean-up.
    $field = NULL;
    $vid = NULL;
    $query = NULL;
    $results = NULL;
    $field_name = NULL;
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
    foreach ($tables as $field_name => $fields) {
      if (!is_array($fields)) {
        $fields = [$fields];
      }
      $table_name = $field_name;
      if (substr($table_name, 0, 11) !== 'field_data_') {
        // If the table name doesn't already have the 'field_data_' prefix,
        // add it.
        $table_name = 'field_data_' . $table_name;
      }
      else {
        // Our field name needs to have 'field_data_' removed from it.
        $field_name = str_replace('field_data_', '', $field_name);
      }
      foreach ($fields as $column_name) {
        if (substr($column_name, 0, strlen($field_name)) !== $field_name) {
          $column_name = $field_name . '_' . $column_name;
        }
        $row->setSourceProperty($column_name, $this->select($table_name, 't')
          ->fields('t', [$column_name])
          ->condition('entity_id', $nid, '=')
          ->execute()
          ->fetchCol());
        $column_name = NULL;
      }
    }
    $table_name = NULL;
    $field_name = NULL;
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
    if ($this->rowCount++ % $size === 0) {
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
      // We have a summary to use, but depending on the D7 setup,
      // it may have still allowed tags and/or we may want to
      // further truncate it still.
      return $this->extractSummaryFromText($field[0]['summary'], $length);
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
    if (!$db->schema()->tableExists('migrate_map_' . $this->migration->id())) {
      return 0;
    }
    $last_migrated = $db->select('migrate_map_' . $this->migration->id(), 'm')
      ->fields('m', ['sourceid1'])
      ->orderBy('sourceid1', 'DESC')
      ->execute()
      ->fetch();
    return $last_migrated->sourceid1 ?? 0;
  }

}
