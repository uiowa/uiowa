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
  public function prepareRow(Row $row) {
    parent::prepareRow($row);

    // Map groups to person types and tags.
    if ($groups = $row->getSourceProperty('field_ref_person_groups_target_id')) {
      $person_types = [];
      $tags = [];
      foreach ($groups as $target_id) {
        if ((int) $target_id === 111) {
          // If Community Partner is present, map to a person type.
          $person_types[] = 'community_partner';
        }
        else {
          // Otherwise, map the group to a tag.
          $tags[] = $this->mapGroupsToTags($target_id);
        }
      }
      $groups = NULL;
    }
    $row->setSourceProperty('person_types', $person_types);
    $row->setSourceProperty('tags', $tags);
    return TRUE;
  }

  /**
   * Map groups to person types.
   */
  private function mapGroupsToTags($target_id) {
    $map = [
      108 => 1,
      109 => 6,
      110 => 11,
    ];

    return $map[$target_id] ?? NULL;
  }

}
