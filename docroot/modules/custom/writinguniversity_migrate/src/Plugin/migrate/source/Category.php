<?php

namespace Drupal\writinguniversity_migrate\Plugin\migrate\source;

use Drupal\Core\Database\Database;
use Drupal\migrate\Plugin\migrate\source\SqlBase;

/**
 * Basic implementation of the source plugin.
 *
 * @MigrateSource(
 *  id = "d7_writinguniversity_blog_categories",
 *  source_module = "writinguniversity_migrate"
 * )
 */
class Category extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('taxonomy_term_data', 'terms')
      ->fields('terms', [
        'tid',
        'vid',
        'name',
        'description',
        'weight',
        'format',
      ])
      // Limit it to only the Categories taxonomy vocabulary.
      ->condition('vid', 2, '=');
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [
      'tid' => $this->t('Term ID.'),
      'vid' => $this->t('Vocabulary ID to which the term belongs.'),
      'name' => $this->t('Term name.'),
      'description' => $this->t('Term description.'),
      'weight' => $this->t('Weight used for term ordering.'),
      'format' => $this->t('The filter format.'),
    ];
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'tid' => [
        'type' => 'integer',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function postImportProcess() {
    // Use the D8 database and grab all our connections.
    $connection = \Drupal::database();
    $nid_mapping = $connection->select('migrate_map_d7_writinguniversity_blog', 'mm')
      ->fields('mm', ['sourceid1', 'destid1'])
      ->execute()
      ->fetchAllKeyed(0, 1);

    $tid_mapping = $connection->select('migrate_map_d7_writinguniversity_blog_categories', 'mc')
      ->fields('mc', ['sourceid1', 'destid1'])
      ->execute()
      ->fetchAllKeyed(0, 1);

    // Switch to the D7 database.
    Database::setActiveConnection('drupal_7');
    $connection = Database::getConnection();
    $query = $connection->select('field_data_taxonomy_vocabulary_2', 'tv');
    $query->fields('tv', [
      'entity_id',
      'delta',
      'taxonomy_vocabulary_2_target_id',
    ]);
    $results = $query->condition('bundle', 'blog_entry_image_large', '=')
      ->execute();

    $to_update = [];
    $entityTypeManager = \Drupal::service('entity_type.manager')
      ->getStorage('node');
    foreach ($results as $result) {
      // Ensure that entity id exists in mapping.
      // @todo Do we need to handle the case where it doesn't?
      if (isset($nid_mapping[$result->entity_id])) {
        // Grab the old nid and map to the new.
        $nid = $nid_mapping[$result->entity_id];
        // Check if we already loaded the node, else load it.
        if (!isset($to_update[$nid])) {
          $to_update[$nid] = $entityTypeManager->load($nid);
        }
        $node = $to_update[$nid];
        // If the node doesn't already reference the tag, append it.
        if (!str_contains($node->get('field_tags')->getString(), $tid_mapping[$result->taxonomy_vocabulary_2_target_id])) {
          $node->get('field_tags')->appendItem($tid_mapping[$result->taxonomy_vocabulary_2_target_id]);
        }
      }
    }
    foreach ($to_update as $nid => $node) {
      $node->save();
    }
    return TRUE;
  }

}
