<?php

namespace Drupal\sitenow;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

class UpdateHelper {

  use StringTranslationTrait;

  public const SECTION_COLUMN = 'layout_builder__layout_section';

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var array
   */
  protected array $tableList = [];

  /**
   * @var array
   */
  protected array $queries = [];

  /**
   * @param \Drupal\Core\Database\Connection $connection
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   */
  public function __construct(Connection $connection, EntityTypeManagerInterface $entity_type_manager) {
    $this->connection = $connection;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * @param $entity_type
   *
   * @return array
   */
  public function getLayoutTables($entity_type) {
    if (!isset($this->getContentEntityTypes()[$entity_type])) {
      return [];
    }

    if (!isset($this->tableList[$entity_type])) {
      $this->tableList[$entity_type] = [];
      if ($this->connection->schema()->tableExists("{$entity_type}__layout_builder__layout")) {
        $this->tableList[$entity_type][] = "{$entity_type}__layout_builder__layout";
      }
      if ($this->getContentEntityTypes()[$entity_type]->isRevisionable() && $this->connection->schema()->tableExists("{$entity_type}_revision__layout_builder__layout")) {
        $this->tableList[$entity_type][] = "{$entity_type}_revision__layout_builder__layout";
      }
    }

    return $this->tableList[$entity_type];
  }

  /**
   * @return \Drupal\Core\Entity\EntityTypeInterface[]
   */
  protected function getContentEntityTypes(): array {
    return array_filter($this->entityTypeManager
      ->getDefinitions(), function ($value) {
        return $value instanceof ContentEntityType;
      });
  }

  /**
   * @param $entity_type
   *
   * @return \Drupal\Core\Database\Query\SelectInterface[]
   */
  public function getQuery($entity_type) {
    if (!isset($this->getContentEntityTypes()[$entity_type])) {
      return [];
    }
    if (!isset($this->queries[$entity_type])) {
      $this->queries[$entity_type] = [];
      foreach ($this->getLayoutTables($entity_type) as $table) {
        $query = $this->connection->select($table, 't');
        $query = $query
          ->condition(static::SECTION_COLUMN, "%$block_plugin_id", 'LIKE')
          ->fields('n', ['entity_id', 'revision_id', 'delta', static::SECTION_COLUMN]);
    }
    return $this->queries[$entity_type];
  }

}
