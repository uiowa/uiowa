<?php

namespace Drupal\sitenow_migrate\EventSubscriber;

use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Database\Connection;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateImportEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber for post-import migrate event.
 *
 * @package Drupal\sitenow_migrate\EventSubscriber
 */
class MigratePostImportEvent implements EventSubscriberInterface {
  use StringTranslationTrait;

  /**
   * The EntityTypeManager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

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
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger interface.
   * @param \Drupal\Core\Database\Connection $connection
   *   Database connection object.
   */
  public function __construct(EntityTypeManager $entityTypeManager, LoggerInterface $logger, Connection $connection) {
    $this->entityTypeManager = $entityTypeManager;
    $this->logger = $logger;
    $this->connection = $connection;
    $this->basePath = explode('/', \Drupal::service('site.path'))[1];;
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

      // Right now, page migration is set to run last.
      // This should only run after it has finished.
      case 'd7_page':
        $this->sourceToDestIds = $this->fetchMapping();
        $this->d7Aliases = $this->fetchAliases(TRUE);
        $this->d8Aliases = $this->fetchAliases();
        $this->logger->notice($this->t('Checking for possible broken links'));
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

      $this->logger->notice($this->t('Checking node id @nid', [
        '@nid' => $candidate,
      ]));

      /** @var \Drupal\node\NodeInterface $node */
      $node = $this->entityTypeManager->getStorage('node')->load($candidate);

      // Depending on the content type, we need to access the content
      // differently. Page pull from paragraphs. Person/Article pull from body.
      switch ($node->getType()) {
        // This is no longer needed, unless we place content in lb.
        case 'page-deprecated':
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
        case 'page':
          $content = $node->body->value;
          break;

      }

      $content = preg_replace_callback('|<a href="(.*?)">|i', [
        $this,
        'linkReplace',
      ], $content);

      // Depending on content type, need to set it differently.
      switch ($node->getType()) {
        // No longer needed, unless we build in lb.
        case 'page-deprecated':
          $paragraph->set('field_text_body', [
            'value' => $content,
            'format' => 'filtered_html',
          ]);
          $paragraph->save();
          break;

        case 'article':
        case 'person':
        case 'page':
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
    $this->logger->notice($this->t('Old link found... @old_link', [
      '@old_link' => $old_link,
    ]));

    // Check if it's a mailto: link and return if it is.
    if (substr($old_link, 0, 7) == 'mailto:') {
      $this->logger->notice($this->t('Mailto link found...skipping.'));
      return $match[0];
    }

    // If it's an anchor link only, we can skip it.
    // Look only for # after the first position.
    if (strpos($old_link, '#', 1)) {
      $split_anchor = explode('#', $old_link);
      $suffix = '#' . $split_anchor[1];
      $old_link = $split_anchor[0];
    }
    else {
      $suffix = '';
    }

    // Check if it's a direct node path.
    if (substr($old_link, 0, 4) == 'node' || substr($old_link, 0, 5) == '/node') {
      // Split and grab the last part
      // which will be the node number.
      $link_parts = explode('/', $old_link);
      $old_nid = end($link_parts);

      // Check that there is a mapping and set it to the new id.
      if (isset($this->sourceToDestIds[$old_id])) {
        $new_nid = $this->sourceToDestIds[$old_id];
        // Display message in terminal.
        $this->logger->notice($this->t('Old nid... @old_nid', [
          '@old_nid' => $old_nid,
        ]));
        $this->logger->notice($this->t('New nid... @new_nid', [
          '@new_nid' => $new_nid,
        ]));
        $new_link = '<a href="/node/' . $new_id . $suffix . '"';
      }
      // No mapping found, so keep the old link.
      else {
        $new_link = $match[0];
        $this->logger->notice($this->t('No mapping found for nid... @old_nid', [
          '@old_nid' => $old_nid,
        ]));
      }
      return $new_link;
    }

    // We have an absolute link--need to check if it references this
    // site or is external site.
    elseif (substr($old_link, 0, 4) == 'http') {
      $pattern = '|"(https?://)?(www.)?(' . $this->basePath . ')/(.*?)"|';
      if (preg_match($pattern, $old_link, $absolute_path)) {
        $d7_nid = $this->d7Aliases[$absolute_path[4]];
        $new_link = (isset($this->sourceToDestIds[$d7_nid])) ?
          '<a href="/node/' . $this->sourceToDestIds[$d7_nid] . '"' :
          '<a href="/' . $absolute_path[4] . $suffix . '"';
        $this->logger->notice($this->t('New link found from absolute path... @new_link', [
          '@new_link' => $new_link,
        ]));

        return $new_link;
      }
    }

    // If we got here, we should have a relative link
    // that isn't in the /node/id format.
    else {
      $d7_nid = $this->d7Aliases[$old_link];
      $new_link = (isset($this->sourceToDestIds[$d7_nid])) ?
        '<a href="/node/' . $this->sourceToDestIds[$d7_nid] . $suffix . '"' :
        $match[0];

      $this->logger->notice($this->t('New link found from /node/ path... @new_link', [
        '@new_link' => $new_link,
      ]));

      return $new_link;
    }

    // No matches were found--return the unchanged original.
    return $match[0];
  }

  /**
   * Query for a list of nodes which may contain newly broken links.
   */
  private function checkForPossibleLinkBreaks() {
    // Check for possible link breaks in standard body fields.
    $query = $this->connection->select('node__body', 'nb')
      ->fields('nb', ['entity_id']);
    $query->condition($query->orConditionGroup()
      ->condition('nb.body_value', $this->basePath, 'LIKE')
      ->condition('nb.body_value', "%<a href%node/%", 'LIKE')
    );
    $result = $query->execute();
    $candidates = $result->fetchCol();

    foreach ($candidates as $candidate) {
      $this->logger->notice($this->t('Possible broken link found in node @candidate', [
        '@candidate' => $candidate,
      ]));
    }

    return $candidates;
  }

  /**
   * Retrieve D7/8 aliases in an indexed array of nid => alias and alias => nid.
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
      $query = $this->connection->select('path_alias', 'pa');
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
  private function fetchMapping($page_id = 'd7_page', $article_id = 'd7_article') {
    if ($this->connection->schema()->tableExists('migrate_map_' . $page_id)) {
      $sub_result1 = $this->connection->select('migrate_map_' . $page_id, 'mm')
        ->fields('mm', ['sourceid1', 'destid1']);
    }
    if ($this->connection->schema()->tableExists('migrate_map_' . $article_id)) {
      $sub_result2 = $this->connection->select('migrate_map_' . $article_id, 'mma')
        ->fields('mma', ['sourceid1', 'destid1']);
      $unioned = isset($sub_result1) ? $sub_result1->union($sub_result2) : $sub_result2;
    }
    else {
      $unioned = $sub_result1;
    }

    $result = $unioned->execute();
    $sourceToDestIds = [];
    foreach ($result as $row) {
      $sourceToDestIds[$row->sourceid1] = $row->destid1;
    }
    return $sourceToDestIds;
  }

}
