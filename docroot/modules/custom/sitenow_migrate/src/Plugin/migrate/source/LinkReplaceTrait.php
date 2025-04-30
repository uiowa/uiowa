<?php

namespace Drupal\sitenow_migrate\Plugin\migrate\source;

use Drupal\Component\Utility\Html;
use Drupal\migrate\Row;

/**
 * Provides functions for processing links in source plugins.
 */
trait LinkReplaceTrait {

  /**
   * Pre-migrate method for replacing links.
   *
   * @param \Drupal\migrate\Row $row
   *   The migration source row.
   * @param string $field_name
   *   The fields that should get link preprocessing.
   * @param int $no_links_prior
   *   The creation year before which links should be removed.
   */
  private function preLinkReplace(Row $row, string $field_name, int $no_links_prior = 0) {
    $field = $row->getSourceProperty($field_name);
    if (!empty($field)) {
      // Search for D7 inline embeds and replace with D8 inline entities.
      $field[0]['value'] = $this->replaceInlineFiles($field[0]['value']);

      // Parse links.
      $doc = Html::load($field[0]['value']);
      $links = $doc->getElementsByTagName('a');
      $i = $links->length - 1;
      $created_year = date('Y', $row->getSourceProperty('created'));

      while ($i >= 0) {
        $link = $links->item($i);
        $href = $link->getAttribute('href');

        // Unlink anchors in body from articles before the given
        // no_links_prior value (default 0).
        if ($created_year < $no_links_prior) {
          $text = $doc->createTextNode($link->nodeValue);
          $link->parentNode->replaceChild($text, $link);
          $doc->saveHTML();
        }
        else {
          $site_path = \str_replace('sites/', '', \Drupal::getContainer()->getParameter('site.path'));
          if (strpos($href, '/node/') === 0 || stristr($href, $site_path . '/node/')) {
            $nid = explode('node/', $href)[1];

            if ($lookup = $this->manualLookup($nid)) {
              $link->setAttribute('href', '/node/' . $lookup);
              $link->parentNode->replaceChild($link, $link);
              $this->getLogger('sitenow_migrate')->info('Replaced internal link @link in article @article.', [
                '@link' => $href,
                '@article' => $row->getSourceProperty('title'),
              ]);

            }
            else {
              $this->getLogger('sitenow_migrate')->notice('Unable to replace internal link @link in article @article.', [
                '@link' => $href,
                '@article' => $row->getSourceProperty('title'),
              ]);
            }
          }
        }

        $i--;
      }

      $html = Html::serialize($doc);
      $field[0]['value'] = $html;

      $row->setSourceProperty($field_name, $field);
    }
  }

  /**
   * Post-migration method of replacing internal links.
   *
   * @param string $entity_type
   *   The entity type on which to replace links.
   * @param array $field_tables
   *   Array of the database tables and columns to check for broken links.
   */
  private function postLinkReplace(string $entity_type, array $field_tables) {
    $this->getLogger('sitenow_migrate')->notice('Beginning link replace.');
    // Initialize our storage manager and our
    // "broken link candidates" list to add to and edit later.
    $entity_manager = \Drupal::service('entity_type.manager')
      ->getStorage($entity_type);
    $candidates = [];

    // Iterate through each of the field tables we need to check.
    // A lot of times, we may only need to check one table, one column.
    foreach ($field_tables as $table => $columns) {
      $results = \Drupal::database()->select($table, 't')
        ->fields('t', array_merge($columns, ['entity_id']))
        ->execute()
        ->fetchAllAssoc('entity_id');
      foreach ($results as $entity_id => $cols) {
        foreach ($cols as $col => $value) {
          // Skip over entity_id if we doubled it up.
          if ($col === 'entity_id') {
            continue;
          }

          // Checks for any links using the node/ format.
          if (preg_match('|<a.*?node.*?>(.*?)<\/a>|i', $value)) {
            // Add the candidate entity_id and field column in which
            // the broken link was discovered.
            $candidates[$entity_id][] = $col;
          }
        }
      }
    }

    // We have our list of candidate entity_ids and fields
    // in which we have a suspicion of broken links.
    foreach ($candidates as $entity_id => $cols) {
      $entity = $entity_manager->load($entity_id);
      $changed = FALSE;

      // We'll check, fix, and set each field individually.
      foreach ($cols as $col) {
        // We want the actual field name, but our candidate list
        // has the database table names, which (should) contain
        // a '_value' suffix. Stripping it out should give us the
        // actual field name in most cases.
        $field_name = str_replace('_value', '', $col);
        $field_data = $entity->get($field_name)->getValue()[0];

        $this->getLogger('sitenow_migrate')->info('Checking @link in entity @entity.', [
          '@link' => $field_name,
          '@entity' => $entity_id,
        ]);

        if (!empty($field_data)) {
          // Load the dom and parse for links.
          $doc = Html::load($field_data['value']);
          $links = $doc->getElementsByTagName('a');
          $i = $links->length - 1;
          while ($i >= 0) {
            $link = $links->item($i);
            $href = $link->getAttribute('href');
            $site_path = \str_replace('sites/', '', \Drupal::getContainer()->getParameter('site.path'));
            if (strpos($href, '/node/') === 0 || stristr($href, $site_path . '/node/')) {
              $nid = explode('node/', $href)[1];
              $nid = explode('#', $nid)[0];

              if ($lookup = $this->manualLookup($nid)) {
                $link->setAttribute('href', '/node/' . $lookup);
                $link->parentNode->replaceChild($link, $link);
                $this->getLogger('sitenow_migrate')->info('Replaced internal link from /node/@nid to /node/@link in entity @entity.', [
                  '@nid' => $nid,
                  '@link' => $lookup,
                  '@entity' => $entity_id,
                ]);
              }
              else {
                $this->getLogger('sitenow_migrate')->notice('Unable to replace internal link @link in entity @entity.', [
                  '@link' => $href,
                  '@entity' => $entity_id,
                ]);
              }
            }

            $i--;
          }
        }

        $html = Html::serialize($doc);
        $field_data['value'] = $html;
        $entity->set($field_name, $field_data);
        $changed = TRUE;
      }
      if ($changed) {
        $entity->save();
      }
    }
  }

  /**
   * Query for a list of nodes which may contain newly broken links.
   *
   * @param array $fields
   *   A [field => column] associative array for database columns
   *   that should be checked for potential broken links.
   * @param array|int $to_exclude
   *   An array of node ids which should be excluded from reporting,
   *   or an int for a high water mark to check after,
   *   for instance if links are known to have been replaced.
   */
  private function checkForPossibleLinkBreaks(array $fields, $to_exclude = []) {
    $candidates = [];
    foreach ($fields as $field => $columns) {
      $query = \Drupal::database()->select($field, 'f')
        ->fields('f', array_merge($columns, ['entity_id']));
      if (is_int($to_exclude)) {
        $query->condition('f.entity_id', $to_exclude, '>');
      }
      elseif (!empty($to_exclude)) {
        $query->condition('f.entity_id', $to_exclude, 'NOT IN');
      }
      $new_candidates = $query->execute()
        ->fetchAllAssoc('entity_id');
      $candidates = array_merge($candidates, $new_candidates);
    }
    return $candidates;
  }

  /**
   * Report a list of nodes with links possibly broken by the migration.
   *
   * @param array $fields
   *   A [field => column] associative array for database columns
   *   that should be checked for potential broken links.
   * @param array|int $to_exclude
   *   An array of node ids which should be excluded from reporting,
   *   or an int for a high water mark to check after,
   *   for instance if links are known to have been replaced.
   */
  private function reportPossibleLinkBreaks(array $fields, $to_exclude = []) {
    $candidates = $this->checkForPossibleLinkBreaks($fields, $to_exclude);
    foreach ($candidates as $entity_id => $cols) {
      $oopsie_daisies = [];
      foreach ($cols as $key => $value) {
        if ($key === 'entity_id') {
          continue;
        }

        // Checks for any links using the node/ format
        // and reports the node id on which it was found
        // and the linked text.
        if (preg_match_all('|<a.*?(node.*?)">(.*?)<\/a>|i', $value, $matches)) {
          $links = [];
          for ($i = 0; $i < count($matches[0]); $i++) {
            $links[] = $matches[2][$i] . ': ' . $matches[1][$i];
          }
          $oopsie_daisies[$entity_id] = implode(',', $links);
        }
      }

      foreach ($oopsie_daisies as $id => $links) {
        $this->getLogger('sitenow_migrate')->notice($this->t('Possible broken links found in node @candidate: @links', [
          '@candidate' => $id,
          '@links' => $links,
        ]));
      }
    }
  }

  /**
   * Update aliases from D7 to newly created D8 references.
   */
  private function updateInternalLinks(array $fields, $to_exclude = []) {
    $candidates = $this->checkForPossibleLinkBreaks($fields, $to_exclude);
    // Each candidate is an nid of a page suspected to contain a broken link.
    foreach ($candidates as $candidate) {

      $this->getLogger('sitenow_migrate')->notice($this->t('Checking node id @nid', [
        '@nid' => $candidate,
      ]));

      /** @var \Drupal\node\NodeInterface $node */
      $node = $this->entityTypeManager->getStorage('node')->load($candidate);

      $this->linkReplace($node);
    }
  }

  /**
   * Regex callback for updating links broken by the migration.
   */
  private function linkReplace($node) {
    $original_nid = $node->id();
    $content = $node?->body?->value;
    $changed = FALSE;

    if (empty($content)) {
      return;
    }

    // Load the dom and parse for links.
    $doc = Html::load($content);
    $links = $doc->getElementsByTagName('a');
    $i = $links->length - 1;

    while ($i >= 0) {
      $link = $links->item($i);
      $href = $link->getAttribute('href');

      // Grab the original site's path from our migration configuration.
      $site_path = $this->configuration['constants']['source_base_path'];

      if (str_starts_with($href, '/node/') || stristr($href, $site_path . '/node/')) {
        $nid = explode('node/', $href)[1];

        $anchor_check = explode('#', $nid);
        $nid = $anchor_check[0];
        $anchor = $anchor_check[1] ? '#' . $anchor_check[1] : '';

        // TODO: write mapLookup.
        if ($lookup = $this->mapLookup($nid)) {
          $link->setAttribute('href', '/node/' . $lookup . $anchor);
          $link->parentNode->replaceChild($link, $link);
          $this->getLogger('sitenow_migrate')->info('Replaced internal link from /node/@nid to /node/@link in entity @entity.', [
            '@nid' => $nid,
            '@link' => $lookup,
            '@entity' => $original_nid,
          ]);

          $changed = TRUE;
        }
        else {
          $this->getLogger('sitenow_migrate')->notice('Unable to replace internal link @link in entity @entity.', [
            '@link' => $href,
            '@entity' => $original_nid,
          ]);
        }
      }

      $i--;
    }

    $html = Html::serialize($doc);
    $node->body->value = $html;

    if ($changed) {
      $node->save();
    }
  }

  /**
   * Override for manual lookup tables of pre-migrated content.
   */
  private function mapLookup(int $nid) {
    if (empty($this->nidMapping)) {
      // If we didn't have a mapping already set, try to make one.
      $map_table_name = $this->migration->getIdMap()->getQualifiedMapTableName();
      // We don't need the "qualified" part, so drop everything
      // before the period.
      $map_table_name = explode('.', $map_table_name)[1];
      $this->nidMapping = $this->fetchMapping($map_table_name);
    }
    if (isset($this->nidMapping[$nid])) {
      return $this->nidMapping[$nid];
    }
    $this->getLogger('sitenow_migrate')->notice(t('Failed to fetch replacement for node id: @nid', [
      '@nid' => $nid,
    ]));
    return $nid;
  }

  /**
   * Query the migration map to get a D7-nid => D8-nid indexed array.
   */
  private function fetchMapping($migrate_maps): array {
    $connection = \Drupal::database();
    // Grab the first map to initiate the query. If there are more
    // they will need to be unioned to this one.
    $first_migrate_map = array_shift($migrate_maps);
    if ($connection->schema()->tableExists($first_migrate_map)) {
      $sub_result = $connection->select($first_migrate_map, 'mm')
        ->fields('mm', ['sourceid1', 'destid1']);
    }
    foreach ($migrate_maps as $migrate_map) {
      if ($connection->schema()->tableExists($migrate_map)) {
        $next_sub_result = $connection->select($migrate_map, 'mm')
          ->fields('mm', ['sourceid1', 'destid1']);
        $sub_result = $sub_result->union($next_sub_result);
      }
    }

    // Return an associative array of
    // source_nid -> destination_nid.
    return $sub_result->execute()
      ->fetchAllKeyed(0, 1);
  }

  /**
   * Override for manual lookup tables of pre-migrated content.
   */
  private function manualLookup(int $nid) {
    return FALSE;
  }

}
