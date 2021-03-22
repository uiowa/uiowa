<?php

namespace Drupal\sitenow_migrate\Plugin\migrate\source;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\State\StateInterface;
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
abstract class BaseNodeSource extends Node {
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
   * Extract a summary from a block of text.
   */
  protected function extractSummaryFromText($text) {
    $new_summary = substr($text, 0, 200);
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
    // Strip out any HTML, and set the new summary.
    $new_summary = preg_replace("|<.*?>|", '', $new_summary);

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
   * @param MigrateImportEvent $event
   *   The migrate import event.
   */
  public function postImportProcess(MigrateImportEvent $event) {}

  /**
   * Attempt to clear the entity cache if needed to avoid memory overflows.
   *
   * Based on core/modules/migrate/src/MigrateExecutable.php, line 543.
   *
   * @return int
   *   Return the existing memory usage.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function clearMemory() {
    // First, try resetting Drupal's static storage - this frequently releases
    // plenty of memory to continue.
    drupal_static_reset();

    // Entity storage can blow up with caches so clear them out.
    $manager = $this->entityTypeManager;
    foreach ($manager->getDefinitions() as $id => $definition) {
      $manager
        ->getStorage($id)
        ->resetCache();
    }

    // Run garbage collector to further reduce memory.
    gc_collect_cycles();
    return memory_get_usage();
  }

}
