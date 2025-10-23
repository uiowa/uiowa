<?php

namespace Drupal\iwp_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;
use Drupal\sitenow_migrate\Plugin\migrate\source\ProcessMediaTrait;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Migrate Source plugin.
 *
 * @MigrateSource(
 *   id = "iwp_bio",
 *   source_module = "node"
 * )
 */
class Bio extends BaseNodeSource {
  use ProcessMediaTrait;

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();
    // Make sure our nodes are retrieved in order
    // for ease of debugging.
    $query->orderBy('nid');
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    parent::prepareRow($row);

    // Skip over the rest of the preprocessing, as it's not needed
    // for redirects. Also avoids duplicating the notices.
    // Return TRUE because the row should be created.
    if ($this->migration->id() === 'iwp_bio_redirects') {
      return TRUE;
    }

    $source_fields = [
      'field_writer_lang' => 'writer_bio_languages',
      'field_writer_session_status' => 'writer_bio_session_status',
    ];

    foreach ($source_fields as $source_field => $vocabulary_machine_name) {
      if ($values = $row->getSourceProperty($source_field)) {
        $term_ids = [];

        foreach ($values as $value) {
          $term_id = $this->createTerm($value, $vocabulary_machine_name);

          if ($term_id) {
            $term_ids[] = $term_id;
          }
        }

        if (!empty($term_ids)) {
          $row->setSourceProperty("{$source_field}_processed", $term_ids);
        }
      }
    }

    $body = $row->getSourceProperty('body');
    if (isset($body)) {
      $body[0]['format'] = 'filtered_html';
      $row->setSourceProperty('body', $body);
    }

    if ($image = $row->getSourceProperty('field_image_attach')) {
      $row->setSourceProperty('field_image_attach', $this->processImageField($image[0]['fid'], $image[0]['alt'], $image[0]['title']));
    }

    if ($file = $row->getSourceProperty('field_writer_sample')) {
      $row->setSourceProperty('field_writer_sample', $this->processFileField($file[0]['fid']));
    }

    if ($file_sample_original = $row->getSourceProperty('field_writing_sample_in_original')) {
      $row->setSourceProperty('field_writing_sample_in_original', $this->processFileField($file_sample_original[0]['fid']));
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function createTerm($term_name, $vocabulary_machine_name) {
    $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

    // Load the vocabulary entity by its machine name.
    $vocabulary = Vocabulary::load($vocabulary_machine_name);

    if (!$vocabulary) {
      return NULL;
    }

    // Check if the term already exists in the vocabulary.
    $existing_term = $term_storage->loadByProperties([
      'name' => $term_name,
      'vid' => $vocabulary->id(),
    ]);

    if ($existing_term) {
      return reset($existing_term)->id();
    }

    // Create a new term if it doesn't exist.
    $term = $term_storage->create([
      'name' => $term_name,
      'vid' => $vocabulary->id(),
    ]);

    $term->save();
    return $term->id();
  }

}
