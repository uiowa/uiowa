<?php

namespace Drupal\uipress_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;
use Drupal\sitenow_migrate\Plugin\migrate\source\ProcessMediaTrait;

/**
 * Migrate Source plugin.
 *
 * @MigrateSource(
 *   id = "uipress_authors",
 *   source_module = "node"
 * )
 */
class Authors extends BaseNodeSource {
  use ProcessMediaTrait;

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();
    $query->leftJoin('url_alias', 'alias', "alias.source = CONCAT('node/', n.nid)");
    $query->fields('alias', ['alias']);
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = parent::fields();
    $fields['alias'] = $this->t('The URL alias for this node.');
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    parent::prepareRow($row);
    // If there's a suffix, append it to the last name field.
    if ($suffix = $row->getSourceProperty('field_author_suffix')) {
      $lastname = $row->getSourceProperty('field_author_lastname');
      $lastname[0]['value'] .= ', ' . $suffix[0]['value'];
      $row->setSourceProperty('field_author_lastname', $lastname);
    }
    // Download image and attach it for the person photo.
    if ($image = $row->getSourceProperty('field_image_attach')) {
      // @todo Check the image dimensions and add a cutoff for too small.
      $row->setSourceProperty('field_image', $this->processImageField($image[0]['fid'], $image[0]['alt'], $image[0]['title']));
    }
    // Check if we have a facebook, and either append (or replace) with
    // the author website.
    if ($facebook = $row->getSourceProperty('field_author_facebook')) {
      $website = $row->getSourceProperty('field_author_url');
      $website = array_merge($website, $facebook);
      $row->setSourceProperty('field_author_url', $website);
    }
    return TRUE;
  }

}
