<?php

namespace Drupal\sitenow_migrate\EventSubscriber;

use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateImportEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class MigratePostImportEvent.
 *
 * @package Drupal\sitenow_migrate\EventSubscriber
 */
class MigratePostImportEvent implements EventSubscriberInterface {

  /**
   * The EntityTypeManager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Indexed array for tracking source nids to destination nids.
   *
   * @var array
   */
  protected $sourceToDestIds;

  /**
   * Array for converting between D7 nids and their associated aliases.
   *
   * @var array
   */
  protected $d7Aliases;

  /**
   * Array for converting between D8 nids and their associated aliases.
   *
   * @var array
   */
  protected $d8Aliases;

  /**
   * Base path of the source website for checking absolute URLs.
   *
   * @var string
   */
  protected $basePath;

  /**
   * MigratePostImportEvent constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The EntityTypeManager service.
   */
  public function __construct(EntityTypeManager $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
    // Switch to the D7 database.
    Database::setActiveConnection('drupal_7');
    $connection = Database::getConnection();
    $query = $connection->select('variable', 'v');
    $query->fields('v', ['value'])
      ->condition('v.name', 'file_public_path', '=');
    $result = $query->execute();
    // Switch back to the D8 database.
    Database::setActiveConnection();
    // Get path from public filepath; we don't have the settings file.
    $this->basePath = explode('/', $result->fetchField())[1];
    // If it's a subdomain site, replace '.' with '/'.
    if (substr($this->basePath, 0, 10) == 'uiowa.edu.') {
      substr_replace($this->basePath, '/', 9, 1);
    }
  }

  /**
   * Get subscribed events.
   *
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[MigrateEvents::POST_IMPORT][] = ['onMigratePostImport'];
    return $events;
  }

  /**
   * Calls for additional processing after each migration has completed.
   *
   * {@inheritdoc}
   */
  public function onMigratePostImport(MigrateImportEvent $event) {
    $migration = $event->getMigration();
    switch ($migration->id()) {

      case 'd7_page':
        $this->source_to_dest_ids = $this->fetchMapping();
        $this->d7Aliases = $this->fetchAliases(TRUE);
        $this->d8Aliases = $this->fetchAliases();
        \Drupal::logger('sitenow_migrate')->notice($this->t('Checking for possible broken links'));
        $candidates = $this->checkForPossibleLinkBreaks();
        $this->updateInternalLinks($candidates);
      case 'd7_file':
      case 'd7_article':
      case 'd7_person':
    }
  }

  /**
   * Update aliases from D7 to newly created D8 references.
   */
  private function updateInternalLinks($candidates) {

    // Each candidate is an nid of a page suspected to contain a broken link.
    foreach ($candidates as $candidate) {

      \Drupal::logger('sitenow_migrate')->notice($this->t('Checking node id @nid', [
        '@nid' => $candidate,
      ]));

      /** @var \Drupal\node\NodeInterface $node */
      $node = $this->entityTypeManager->getStorage('node')->load($candidate);

      // Depending on the content type, we need to access the content differently. Page pull from paragraphs. Person/Article pull from body.
      switch ($node->getType()) {
        case 'page':
          $section_target = $node->get('field_page_content_block')->getValue();

          /** @var \Drupal\paragraphs\ParagraphInterface $section */
          $section = $this->entityTypeManager->getStorage('paragraph')->load($section_target[0]['target_id']);

          $paragraph_target = $section->get('field_section_content_block')->getValue();

          /** @var \Drupal\paragraphs\ParagraphInterface $paragraph */
          $paragraph = $this->entityTypeManager->getStorage('paragraph')->load($paragraph_target[0]['target_id']);

          $content = $paragraph->get('field_text_body')->getValue()[0]['value'];
          break;

        case 'article':
        case 'person':
          $content = $node->body->value;
          break;

      }

      $content = preg_replace_callback('|<a href=(".*?")|i', [$this, 'linkReplace'], $content);

      // Depending on content type, need to set it differently.
      switch ($node->getType()) {
        case 'page':
          $paragraph->set('field_text_body', [
            'value' => $content,
          ]);
          $paragraph->save();
          break;

        case 'article':
        case 'person':
          $node->body->value = $content;
          break;
      }
      $node->save();
    }
  }

  /**
   * Regex callback for updating links broken by the migration.
   */
  private function linkReplace($match) {
    $old_link = $match[1];
    \Drupal::logger('sitenow_migrate')->notice($this->t('Old link found... @old_link', [
      '@old_link' => $old_link,
    ]));

    // Check if it's a relative link.
    if (substr($old_link, 1, 1) == '/') {
      $link_parts = explode('/', $old_link);

      // Old node/# formatted links just need the updated mapping.
      if ($link_parts[1] == 'node') {
        // Take the node id, but don't grab the trailing ".
        $old_nid = substr($link_parts[2], 0, -1);
        // If we don't have the correct mapping, return the original link.
        $new_link = (isset($this->source_to_dest_ids[$old_nid])) ? '<a href="/node/' . $this->source_to_dest_ids[$old_nid] . '"' : $match[0];
      }
      else {
        // If it wasn't in node/# format, we need to use the alias (w/out preceding "/ or the trailing ") to get the correct mapping.
        $d7_nid = $this->d7Aliases[substr($old_link, 2, -1)];
        $new_link = (isset($this->source_to_dest_ids[$d7_nid])) ? '<a href="/node/' . $this->source_to_dest_ids[$d7_nid] . '"' : $match[0];
      }
      \Drupal::logger('sitenow_migrate')->notice($this->t('New link found... @new_link', [
        '@new_link' => $new_link,
      ]));

      return $new_link;
    }
    else {
      // We have an absolute link--need to check if it references this site or is external.
      $pattern = '|"(https?://)?(www.)?(' . $this->basePath . ')/(.*?)"|';
      if (preg_match($pattern, $old_link, $match)) {
        $d7_nid = $this->d7Aliases[$match[4]];
        $new_link = (isset($this->source_to_dest_ids[$d7_nid])) ? '<a href="/node/' . $this->source_to_dest_ids[$d7_nid] . '"' : $match[0];
        \Drupal::logger('sitenow_migrate')->notice($this->t('New link found... @new_link', [
          '@new_link' => $new_link,
        ]));

        return $new_link;
      }
    }

    // No matches were found--return the unchanged original.
    return $match;
  }

  /**
   * Query for a list of nodes which may contain newly broken links as a result of the migration.
   */
  private function checkForPossibleLinkBreaks() {
    $candidates = [];
    // Check for possible link breaks in paragraph fields within pages.
    $connection = \Drupal::database();
    $query = $connection->select('node__field_page_content_block', 'n');
    $query->join('paragraph__field_section_content_block', 's', 's.entity_id = n.field_page_content_block_target_id');
    $query->join('paragraph__field_text_body', 'p', 'p.entity_id = s.field_section_content_block_target_id');
    $query->fields('n', ['entity_id'])
      // ->condition($query->orConditionGroup()
        // ->condition('p.field_text_body_value', '%' . $this->basePath . '%', 'LIKE')
        // ->condition('p.field_text_body_value', '%<a href="/node/%"%', 'LIKE')
      ->condition('p.field_text_body_value', '<a href ?= ?[\'"](.*?)(/?node/(\d+))?(' . $this->basePath . ')?', 'REGEXP');
    // );
    $result = $query->execute();
    $candidates = array_merge($candidates, $result->fetchCol());

    // Now check for possible link breaks in standard body fields (articles and people content types).
    $query = $connection->select('node__body', 'nb')
      ->fields('nb', ['entity_id'])
      // ->condition($query->orConditionGroup()
        // ->condition('nb.body_value', $this->basePath, 'LIKE')
        // ->condition('nb.body_value', "%<a href%node/%", 'LIKE')
      // );
      ->condition('nb.body_value', '<a href ?= ?[\'"](.*?)(/?node/(\d+))?(' . $this->basePath . ')?', 'REGEXP');
    $result = $query->execute();
    $candidates = array_merge($candidates, $result->fetchCol());

    foreach ($candidates as $candidate) {
      \Drupal::logger('sitenow_migrate')->notice($this->t('Possible broken link found in node @candidate', [
        '@candidate' => $candidate,
      ]));
    }

    return $candidates;
  }

  /**
   * Retreive D8 or D7 aliases in an indexed array of nid => alias and alias => nid.
   */
  private function fetchAliases($drupal7 = FALSE) {
    if ($drupal7) {
      // Switch to the D7 database.
      Database::setActiveConnection('drupal_7');
      $connection = Database::getConnection();
      $query = $connection->select('url_alias', 'ua');
      $query->fields('ua', ['source', 'alias']);
      $result = $query->execute();
      // Switch back to the D8 database.
      Database::setActiveConnection();
    }
    else {
      $connection = \Drupal::database();
      $query = $connection->select('path_alias', 'pa');
      $query->fields('pa', ['path', 'alias']);
      $result = $query->execute();
    }

    $aliases = [];
    // Pull out the nids and create our nid=>alias, alias=>nid indexer.
    foreach ($result as $row) {
      $source_path = ($drupal7) ? $row->source : $row->path;
      preg_match("|\d+|", $source_path, $match);
      $nid = $match[0];
      $aliases[$nid] = $row->alias;
      $aliases[$row->alias] = $nid;
    }

    return $aliases;
  }

  /**
   * Query the migration map to get a D7-nid => D8-nid indexed array.
   */
  private function fetchMapping() {
    $connection = \Drupal::database();
    $sub_result1 = $connection->select('migrate_map_d7_page', 'mm')
      ->fields('mm', ['sourceid1', 'destid1']);
    $sub_result2 = $connection->select('migrate_map_d7_article', 'mma')
      ->fields('mma', ['sourceid1', 'destid1']);
    $result = $sub_result1->union($sub_result2)
      ->execute();
    $sourceToDestIds = [];
    foreach ($result as $row) {
      $sourceToDestIds[$row->sourceid1] = $row->destid1;
    }
    return $sourceToDestIds;
  }

}
