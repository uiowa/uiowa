<?php

namespace Drupal\iisc_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;
use Drupal\sitenow_migrate\Plugin\migrate\source\ProcessMediaTrait;

/**
 * Migrate Source plugin.
 *
 * @MigrateSource(
 *   id = "iisc_people",
 *   source_module = "node"
 * )
 */
class People extends BaseNodeSource {
  use ProcessMediaTrait;

  /**
   * {@inheritdoc}
   */
  protected $multiValueFields = [
    'field_person_email' => 'email',
    'field_person_telephone' => 'value',
    'field_ref_person_groups' => 'target_id',
  ];

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();
    // Only add the aliases to the query if we're
    // in the redirect migration, otherwise row counts
    // will be off due to one-to-many mapping of nodes to aliases.
    if ($this->migration->id() === 'iisc_people_redirects') {
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
    // Download image and attach it for the person photo.
    if ($image = $row->getSourceProperty('field_image')) {
      $this->entityId = $row->getSourceProperty('nid');
      $row->setSourceProperty('field_image', $this->processImageField($image[0]['fid'], $image[0]['alt'], $image[0]['title']));
    }
    $image = NULL;

    if ($body = $row->getSourceProperty('body')) {
      // Extract the summary.
      $row->setSourceProperty('body_summary', $this->getSummaryFromTextField($body));
    }

    // @todo Map group(s) and IISC role to person types.
    $person_types = [];
    if ($groups = $row->getSourceProperty('field_ref_person_groups_target_id')) {
      foreach ($groups as $target_id) {
        if ($this->groupMap($target_id)) {
          $person_types[] = $this->groupMap($target_id);
        }
      }
    }
    $row->setSourceProperty('person_types', $person_types);
    return TRUE;
  }

  private function groupMap($target_id) {
    $map = [
      108 => 'iisc_staff',
      109 => 'iisc_faculty_staff_network',
      110 => 'affiliated_faculty_and_staff',
      111 => 'community_partner',
    ];

    return $map[$target_id] ?? NULL;
  }

}
