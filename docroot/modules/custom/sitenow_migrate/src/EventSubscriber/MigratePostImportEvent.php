<?php

namespace Drupal\sitenow_migrate\EventSubscriber;

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

  protected $source_to_dest_ids;
  protected $d7_aliases;
  protected $d8_aliases;

  protected $base_path;

  /**
   * MigratePostImportEvent constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The EntityTypeManager service.
   */
  public function __construct(EntityTypeManager $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->source_to_dest_ids = $this->fetchMapping();
    $this->d7_aliases = $this->fetchAliases(True);
    $this->d8_aliases = $this->fetchAliases();
    $this->base_path = \Drupal::urlGenerator()->generateFromRoute('<front>', [], ['absolute' => TRUE]);
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
        \Drupal::logger('sitenow_migrate')->notice(t('Checking for possible broken links'));
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

      \Drupal::logger('sitenow_migrate')->notice(t('Checking node id @nid', [
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

      $pattern = '|<a href="(.*?)"|i';
      \Drupal::logger('sitenow_migrate')->notice(t('Original content... @old_link', [
        '@old_link' => $content,
      ]));
      $content = preg_replace_callback('|<a href="(.*?)"|i', [$this, 'linkReplace'], $content);

      // Depending on content type, need to set it differently.
      switch ($node->getType()) {
        case 'page':
          $paragraph->set('field_text_body', [
            'value' => $content
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
  \Drupal::logger('sitenow_migrate')->notice(t('Old link found... @old_link', [
    '@old_link' => $old_link,
  ]));
  $link_parts = explode('/', $old_link);
  // Old node/# formatted links just need the updated mapping.
  if ($link_parts[0] == 'node' || $link_parts[1] == 'node') {
    $new_link = '/node/' . $this->source_to_dest_ids[$link_parts[2]];
    \Drupal::logger('sitenow_migrate')->notice(t('New link found... @new_link', [
      '@new_link' => $new_link,
    ]));
    return '<a href="' . $new_link . '"';
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
      ->condition($query->orConditionGroup()
        ->condition('p.field_text_body_value', "%<a href=\"@BASE_URL%", 'LIKE')
        ->condition('p.field_text_body_value', "%<a href%node/%", 'LIKE')
      );
    $result = $query->execute();
    $candidates = array_merge($candidates, $result->fetchCol());

    // Now check for possible link breaks in standard body fields.
    
    foreach ($candidates as $candidate) {
      \Drupal::logger('sitenow_migrate')->notice(t('Possible broken link found in node @candidate', [
        '@candidate' =>$candidate,
      ]));
    }

    return $candidates;
  }

  /**
   * Retreive D8 or D7 aliases in an indexed array of nid => alias and alias => nid.
   */
  private function fetchAliases($DRUPAL_7 = False) {
    if ($DRUPAL_7) {
      // Switch to the D7 database.
      \Drupal\Core\Database\Database::setActiveConnection('drupal_7');
      $connection = \Drupal\Core\Database\Database::getConnection();
      $query = $connection->select('url_alias', 'ua');
      $query->fields('ua', ['source', 'alias']);
      $result = $query->execute();
      // Switch back to the D8 database.
      \Drupal\Core\Database\Database::setActiveConnection();
    } else {
      $connection = \Drupal::database();
      $query = $connection->select('path_alias', 'pa');
      $query->fields('pa', ['path', 'alias']);
      $result = $query->execute();
    }

    $aliases = [];
    foreach ($result as $row) {
      $source_path = ($DRUPAL_7) ? 'source' : 'path';
      preg_match("|node/(.*?)|", $source_path, $match);
      $nid = $match[1];
      $aliases[$nid] = $row->alias;
      $aliases[$row->alias] = $nid;
    }

    return $aliases;
  }

  /**
   * Query the migration map to get a D7-nid => D8-nid indexted array.
   */
  private function fetchMapping() {
    $connection = \Drupal::database();
    $sub_result1 = $connection->select('migrate_map_d7_page', 'mm')
      ->fields('mm', ['sourceid1', 'destid1']);
    $sub_result2 = $connection->select('migrate_map_d7_article', 'mma')
      ->fields('mma', ['sourceid1', 'destid1']);
    $result = $sub_result1->union($sub_result2)
      ->execute();
    $source_to_dest_ids = [];
    foreach ($result as $row) {
      $source_to_dest_ids[$row->sourceid1] = $row->destid1;
      \Drupal::logger('sitenow_migrate')->notice(t('Mapping found from source @source to destination @destination', [
        '@source' =>$row->sourceid1,
        '@destination' => $row->destid1,
      ]));
    }
    return $source_to_dest_ids;
  }
}