<?php

namespace Drupal\sitenow_migrate\Plugin\migrate\process;

use Drupal\Core\Database\Database;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\taxonomy\Entity\Term;

/**
 * Map and possibly create new taxonomy terms from a term reference field.
 *
 * Term creation and destination vocabulary can be specified:
 * @code
 *  term_reference_field:
 *    plugin: map_taxonomy
 *    source: source_field
 *    create_new: true
 *    vocabulary: destination_vocab_name
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "map_taxonomy"
 * )
 */
class MapTaxonomy extends ProcessPluginBase {

  /**
   * Whether new terms should be created.
   *
   * @var bool
   */
  protected $createNew = TRUE;

  /**
   * The vocabulary to use for the terms.
   *
   * @var string
   */
  protected $vocabulary = 'tags';

  /**
   * The source database connection.
   *
   * @var Drupal\Core\Database\Database
   */
  protected $database;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->database = Database::getConnection('default', 'drupal_7');
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if ($this->configuration['create_new']) {
      $this->createNew = $this->configuration['create_new'];
    };
    if ($this->configuration['vocabulary']) {
      $this->vocabulary = $this->configuration['vocabulary'];
    };
    $term_name = $this->fetchTermName($value);
    if ($term_name) {
      $tid = $this->fetchTag($term_name);
      if ($tid) {
        return $tid;
      }
    }
    return NULL;
  }

  /**
   * Helper function to fetch existing tags.
   */
  private function fetchTag($term_name) {
    // Check if we already have the tag in the destination.
    $result = \Drupal::database()
      ->select('taxonomy_term_field_data', 't')
      ->fields('t', ['tid'])
      ->condition('t.name', $term_name, '=')
      ->condition('t.vid', $this->vocabulary, '=')
      ->execute()
      ->fetchField();
    if ($result) {
      return $result;
    }
    // If we didn't have the tag already,
    // then create a new tag and return its id.
    if ($this->createNew) {
      $term = Term::create([
        'name' => $term_name,
        'vid' => $this->vocabulary,
      ]);
      if ($term->save()) {
        return $term->id();
      }
    }
    return FALSE;
  }

  /**
   * Helper function to fetch tag name based on source tag id.
   */
  private function fetchTermName($value) {
    if (!isset($value['tid'])) {
      return FALSE;
    }
    return $this->database->select('taxonomy_term_data', 't')
      ->fields('t', ['name'])
      ->condition('t.tid', $value['tid'])
      ->execute()
      ->fetchCol();
  }

}
