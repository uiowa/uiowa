<?php

namespace Drupal\uipress_migrate\Plugin\migrate\source;

use Drupal\migrate\Event\MigrateImportEvent;
use Drupal\migrate\Row;
use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;
use Drupal\sitenow_migrate\Plugin\migrate\source\ProcessMediaTrait;

/**
 * Migrate Source plugin.
 *
 * @MigrateSource(
 *   id = "uipress_books",
 *   source_module = "node"
 * )
 */
class Books extends BaseNodeSource {
  use ProcessMediaTrait;

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();
    // Only add the aliases to the query if we're
    // in the redirect migration, otherwise row counts
    // will be off due to one-to-many mapping of nodes to aliases.
    if ($this->migration->id() === 'uipress_book_redirects') {
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
    // Fetch the multi-value roles.
    $tables = [
      'field_data_field_uibook_series' => ['field_uibook_series_value'],
    ];
    $this->fetchAdditionalFields($row, $tables);
    $series = $row->getSourceProperty('field_uibook_series_value');
    $types = [];
    foreach ($series as $item) {
      $types[] = $this->seriesMapping($item);
    }
    $row->setSourceProperty('field_uibook_series_value', $types);

    // Download image and attach it for the book cover.
    if ($image = $row->getSourceProperty('field_image_attach')) {
      // Set image size minimums.
      $this->imageSizeRestrict = [
        'width' => 300,
        'height' => -1,
        'skip' => FALSE,
      ];
      $this->entityId = $row->getSourceProperty('nid');
      $row->setSourceProperty('field_image', $this->processImageField($image[0]['fid'], $image[0]['alt'], $image[0]['title']));
    }
    return TRUE;
  }

  /**
   * Helper function to map series from the old site to the new site.
   */
  private function seriesMapping($title) {
    // D7 field_uibook_series value => D9 term ID
    $mapping = [
      'American Land and Life Series' => 91,
      'Bur Oak Books' => 106,
      'Bur Oak Guides' => 121,
      'Contemporary North American Poetry Series' => 136,
      'Fan Studies' => 101,
      'Fandom & Culture' => 56,
      'Food and Agriculture' => 116,
      'FoodStory' => 61,
      'Humanities and Public Life' => 11,
      'Impressions: Studies in the Art, Culture, and Future of Books' => 81,
      'Iowa and the Midwest Experience' => 46,
      'Iowa Poetry Prize' => 111,
      'Iowa Prize for Literary Nonfiction' => 1,
      'Iowa Review Series in Fiction' => 26,
      'Iowa Series in Andean Studies' => 86,
      'Iowa Short Fiction Award' => 131,
      'Iowa Szathmáry Culinary Arts Series' => 66,
      'Iowa Whitman Series' => 51,
      'John Simmons Short Fiction Award' => 126,
      'Kuhl House Poets' => 96,
      'Muse Books: The Iowa Series in Creativity and Writing' => 6,
      'Prairie Lights Books' => 71,
      'Sightline Books: The Iowa Series in Literary Nonfiction' => 16,
      'Singular Lives: The Iowa Series in North American Autobiography' => 76,
      'Studies in Theatre History and Culture' => 31,
      'The New American Canon: The Iowa Series in Contemporary Literature and Culture' => 36,
      'The New Neuroscience' => 41,
      'University of Iowa Faculty Connections' => 141,
      'Writers in Their Own Time' => 21,
    ];

    return $mapping[$title] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function postImport(MigrateImportEvent $event) {
    parent::postImport($event);
    // If nothing to report, then we're done.
    if (empty($this->reporter)) {
      return;
    }
    // Grab our migration map.
    $db = \Drupal::database();
    if (!$db->schema()->tableExists('migrate_map_' . $this->migration->id())) {
      return;
    }
    $mapper = $db->select('migrate_map_' . $this->migration->id(), 'm')
      ->fields('m', ['sourceid1', 'destid1'])
      ->execute()
      ->fetchAllKeyed();
    // Update a reporter for new node ids based on old entity ids.
    $reporter = [];
    foreach ($this->reporter as $entity_id => $filename) {
      $reporter[$mapper[$entity_id]] = $filename;
    }
    // Empty it out so it doesn't keep repeating if the postImport
    // runs multiple times, as it sometimes does.
    $this->reporter = [];
    // Spit out a report in the logs/cli.
    foreach ($reporter as $entity_id => $filename) {
      $this->logger->notice('Node: @nid, Image: @filename', [
        '@nid' => $entity_id,
        '@filename' => $filename,
      ]);
    }
  }

}
