<?php

namespace Drupal\sitenow_migrate\Plugin\migrate\process;

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
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if ($this->configuration['create_new']) {
      $this->createNew = $this->configuration['create_new'];
    };
    if ($this->configuration['vocabulary']) {
      $this->vocabulary = $this->configuration['vocabulary'];
    };
    // If we have a basic string, proceed directly to the extraction.
    if (is_string($value)) {
      return $this->extractSummaryFromText($value, $this->length);
    }
    // If we have an array, we need to do some extra checking.
    return $this->getSummaryFromTextField($value, $this->length);
  }


  /**
   * Helper function to fetch existing tags.
   */
  private function fetchTag($value) {
    // Fetch the name from the source based on its term id.
    $term_name = fetchTermName($value);
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
    $term = Term::create([
      'name' => $term_name,
      'vid' => $this->vocabulary,
    ]);
    if ($term->save()) {
      return $term->id();
    }
  }

  /**
   * Helper function to fetch tag name based on source tag id.
   */
  private function fetchTermName($value) {
    // Check the source database for term name.
    return $value;
  }

}
