<?php

namespace Drupal\writinguniversity_migrate\Plugin\migrate\source;

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

}
