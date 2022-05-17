<?php

namespace Drupal\iisc_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;
use Drupal\sitenow_migrate\Plugin\migrate\source\ProcessMediaTrait;

/**
 * Migrate Source plugin.
 *
 * @MigrateSource(
 *   id = "iisc_projects",
 *   source_module = "node"
 * )
 */
class Projects extends BaseNodeSource {
  use ProcessMediaTrait;

  /**
   * {@inheritdoc}
   */
  protected $multiValueFields = [
//    'field_files' => 'fid',
//    'field_project_urls' => ['title', 'url'],
    'field_ref_academic_year' => 'target_id',
  ];

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();
    // Only add the aliases to the query if we're
    // in the redirect migration, otherwise row counts
    // will be off due to one-to-many mapping of nodes to aliases.
    if ($this->migration->id() === 'iisc_project_redirects') {
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
    $fields['alias'] = $this->t('The URL alias for this node.');
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    parent::prepareRow($row);

    // Process the body field for embedded media.
    $body = $row->getSourceProperty('body');
    if (!empty($body)) {
      // Search for D7 inline embeds and replace with D8 inline entities.
      $body[0]['value'] = $this->replaceRelLinkedFiles($body[0]['value']);
      $row->setSourceProperty('body', $body);
    }
    $body = NULL;

    // Download image and attach it for the book cover.
    if ($image = $row->getSourceProperty('field_image')) {
      $this->entityId = $row->getSourceProperty('nid');
      $row->setSourceProperty('field_image', $this->processImageField($image[0]['fid'], $image[0]['alt'], $image[0]['title']));
      $image = NULL;
    }

    // If we have an upload or uploads, process into mids.
    if ($uploads = $row->getSourceProperty('field_files_fid')) {
      foreach ($uploads as $delta => $fid) {
        $uploads[$delta] = $this->processFileField($fid);
      }
      $row->setSourceProperty('field_files_fid', $uploads);
      $uploads = NULL;
    }

    // Process academic years from term to select list.
    if ($years = $row->getSourceProperty('field_ref_academic_year_target_id')) {
      foreach ($years as $delta => $target_id) {
        $years[$delta] = $this->mapAcademicYearTargetIdToValue($target_id);
      }
      $row->setSourceProperty('field_ref_academic_year_target_id', $years);
      $years = NULL;
    }

    return TRUE;
  }

  private function mapAcademicYearTargetIdToValue($target_id) {
    $map = [
      100 => 2009,
      101 => 2010,
      102 => 2011,
      103 => 2012,
      104 => 2013,
      105 => 2014,
      106 => 2015,
      107 => 2016,
      176 => 2017,
      431 => 2018,
      436 => 2019,
      471 => 2020,
      476 => 2021,
    ];

    return $map[$target_id] ?? NULL;
  }

}
