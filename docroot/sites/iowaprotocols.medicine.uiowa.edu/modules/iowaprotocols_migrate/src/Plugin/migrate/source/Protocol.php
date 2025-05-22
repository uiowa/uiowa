<?php

namespace Drupal\iowaprotocols_migrate\Plugin\migrate\source;

use Drupal\migrate\Event\MigrateImportEvent;
use Drupal\migrate\Row;
use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;
use Drupal\sitenow_migrate\Plugin\migrate\source\ProcessMediaTrait;
use Drupal\taxonomy\Entity\Term;
use Drupal\sitenow_migrate\Plugin\migrate\source\LinkReplaceTrait;

/**
 * Migrate Source plugin.
 *
 * @MigrateSource(
 *   id = "protocol",
 *   source_module = "node"
 * )
 */
class Protocol extends BaseNodeSource {

  use ProcessMediaTrait;
  use LinkReplaceTrait;

  /**
   * Tag-to-name mapping for keywords.
   *
   * @var array
   */
  protected $tagMapping;

  /**
   * Tag-to-name mapping for keywords.
   *
   * @var array
   */
  protected $nidMapping;

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();
    // Targeted migration debugging.
    // @todo Remove this when done.
    $query->condition('n.nid', [1191, 8251], 'IN');
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    // Skip this node if it comes after our last migrated.
    if ($row->getSourceProperty('nid') < $this->getLastMigrated()) {
      return FALSE;
    }
    parent::prepareRow($row);

    // Establish an array to eventually map to field_tags.
    $tids = [];

    // Set our tagMapping if it's not already.
    if (empty($this->tagMapping)) {
      $this->tagMapping = \Drupal::database()
        ->select('taxonomy_term_field_data', 't')
        ->fields('t', ['name', 'tid'])
        ->condition('t.vid', 'tags', '=')
        ->execute()
        ->fetchAllKeyed();
    }

    // Process the gallery images from field_article_gallery.
    $gallery = $row->getSourceProperty('field_basic_page_gallery');
    if (!empty($gallery)) {
      // The d7 galleries are a separate entity, so we need to fetch it
      // and then process the individual images attached.
      $gallery_query = $this->select('field_data_field_basic_page_gallery', 'g')
        ->fields('g')
        ->condition('g.entity_id', $row->getSourceProperty('nid'), '=');
      // Grab title and alt directly from these tables,
      // as they are the most accurate for the photo gallery images.
      $gallery_query->leftJoin('field_data_field_file_image_title_text', 'title', 'g.field_basic_page_gallery_fid = title.entity_id');
      $gallery_query->leftJoin('field_data_field_file_image_alt_text', 'alt', 'g.field_basic_page_gallery_fid = alt.entity_id');
      $images = $gallery_query->fields('title')
        ->fields('alt')
        ->execute();
      $new_images = [];
      foreach ($images as $image) {
        // On the source site, the image title is used as the caption
        // in photo galleries, so pass it in as the global caption
        // parameter for the new site.
        $metadata = [
          'title' => $image['field_file_image_title_text_value'] ?? '',
          'alt' => $image['field_file_image_alt_text_value'] ?? '',
        ];
        $new_images[] = $this->processImageField($image['field_basic_page_gallery_fid'], $metadata['alt'], $metadata['title'], $metadata['title']);
      }
      $row->setSourceProperty('gallery', $new_images);
    }

    // Replace inline files and images in the body,
    // and set for placement in the body and teaser fields.
    $body = $row->getSourceProperty('body');
    if (!empty($body)) {
      $this->viewMode = 'large';
      $this->align = 'center';
      // Search for D7 inline embeds and replace with D8 inline entities.
      $body[0]['value'] = $this->replaceInlineImages($body[0]['value'], '/sites/medicine.uiowa.edu.iowaprotocols/files/');
      $body[0]['value'] = $this->replaceInlineFiles($body[0]['value']);

      // Set the format to filtered_html while we have it.
      $body[0]['format'] = 'filtered_html';

      $row->setSourceProperty('body', $body);
    }

    // Process the gallery images from field_article_gallery.
    $category = $row->getSourceProperty('field_category')[0]["value"] ?? NULL;
    if (!empty($category)) {
      $tid = $this->createTag($category);
      $tids[] = $tid;
    }

    // Send all final tids to field_tags.
    if (!empty($tids)) {
      $row->setSourceProperty('tags', $tids);
    }

    return TRUE;
  }

  /**
   * Helper function to check for existing tags and create if they don't exist.
   */
  private function createTag($tag_name) {
    // Check if we have a mapping. If we don't yet,
    // then create a new tag and add it to our map.
    if (!isset($this->tagMapping[$tag_name])) {
      $term = Term::create([
        'name' => $tag_name,
        'vid' => 'tags',
      ]);
      if ($term->save()) {
        $this->tagMapping[$tag_name] = $term->id();
      }
    }

    // Return tid for mapping to field.
    return $this->tagMapping[$tag_name];
  }

  /**
   * {@inheritdoc}
   */
  public function postImport(MigrateImportEvent $event) {
    parent::postImport($event);
    // If we haven't finished our migration,
    // don't proceed with the following.
    $migration = $event->getMigration();
    if (!$migration->allRowsProcessed() || $migration->id() === 'iowaprotocols_page') {
      return;
    }
    $this->getLogger('sitenow_migrate')->notice($this->t('Updating broken links'));
    $this->nidMapping = $this->fetchMapping(['migrate_map_iowaprotocols_protocols', 'migrate_map_iowaprotocols_page']);
    $this->updateInternalLinks(['node__body' => ['body_value']]);

    $this->getLogger('sitenow_migrate')->notice('WE HAVE MIGROTE.');
  }

}
