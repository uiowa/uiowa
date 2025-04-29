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
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
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
      $new_images = [];
      foreach ($gallery as $gallery_image) {
        $new_images[] = $this->processImageField(
          $gallery_image['fid'],
          $gallery_image['alt'],
          $gallery_image['title'],
          $gallery_image['title']
        );
      }
      $row->setSourceProperty('gallery', $new_images);
    }

    // Replace inline files and images in the body,
    // and set for placement in the body and teaser fields.
    $body = $row->getSourceProperty('body');
    if (!empty($body)) {
      $this->viewMode = 'large';
      $this->align = 'left';
      // Search for D7 inline embeds and replace with D8 inline entities.
      $body[0]['value'] = $this->replaceInlineFiles($body[0]['value']);

      // Set the format to filtered_html while we have it.
      $body[0]['format'] = 'filtered_html';

      $row->setSourceProperty('body', $body);
    }

    // Process the gallery images from field_article_gallery.
    $category = $row->getSourceProperty('field_category')[0]["value"] ?? null;
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
    // If we haven't finished our migration, or
    // if we're doing the redirects migration,
    // don't proceed with the following.
    $migration = $event->getMigration();
    if (!$migration->allRowsProcessed() || $migration->id() === 'iowaprotocols_page') {
      return;
    }

    switch ($migration->id()) {

      // Right now, page migration is set to run last.
      // This should only run after it has finished.
      case 'iowaprotocols_protocols':
        $sourceToDestIds = $this->fetchMapping(['d7_page_migration_map']);
        $d7Aliases = $this->fetchAliases(TRUE);
        $d8Aliases = $this->fetchAliases();
        $this->logger->notice($this->t('Checking for possible broken links'));
        $candidates = $this->checkForPossibleLinkBreaks();
        $this->updateInternalLinks($candidates);

      case 'd7_file':
      case 'd7_article':
      case 'd7_person':
    }

    $this->getLogger('sitenow_migrate')->notice('WE HAVE MIGROTE.');
    // Report possible broken links after our known high water mark
    // of articles in which we fixed links.
//    $this->reportPossibleLinkBreaks(['node__body' => ['body_value']]);
  }
}
