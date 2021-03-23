<?php

namespace Drupal\sitenow_migrate\Plugin\migrate\source;

use Drupal\Core\Database\Database;

/**
 * Provides functions for processing links in source plugins.
 */
trait LinkReplaceTrait {

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
  private function reportPossibleLinkBreaks($fields) {
    foreach ($fields as $field => $columns) {
      $candidates = \Drupal::database()->select($field, 'f')
        ->fields('f', array_merge($columns, ['entity_id']))
        ->execute()
        ->fetchAllAssoc('entity_id');

      foreach ($candidates as $entity_id => $cols) {
        $oopsie_daisies = [];
        foreach ($cols as $key => $value) {
          if ($key === 'entity_id') {
            continue;
          }

          if (preg_match_all('|<a.*?>(.*?)<\/a>|i', $value, $matches)) {
            $oopsie_daisies[$entity_id] = implode(',', $matches[1]);
          }
        }

        foreach ($oopsie_daisies as $id => $links) {
          $this->logger->notice($this->t('Possible broken links found in node @candidate: @links', [
            '@candidate' => $id,
            '@links' => $links,
          ]));
        }
      }
    }
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
   *
   * @todo Use the actual migrate map service.
   */
  private function fetchMapping($migrate_maps) {
    // Grab the first map to initiate the query. If there are more
    // they will need to be unioned to this one.
    $first_migrate_map = array_shift($migrate_maps);
    if ($this->connection->schema()->tableExists($first_migrate_map)) {
      $sub_result = $this->connection->select($first_migrate_map, 'mm')
        ->fields('mm', ['sourceid1', 'destid1']);
    }
    // @todo handle a missing first migrate_map.
    else {
      return FALSE;
    }
    foreach ($migrate_maps as $migrate_map) {
      if ($this->connection->schema()->tableExists($migrate_map)) {
        $next_sub_result = $this->connection->select($migrate_map, 'mm')
          ->fields('mm', ['sourceid1', 'destid1']);
        $sub_result = $sub_result->union($next_sub_result);
      }
    }

    // Return an associative array of
    // source_nid -> destination_nid.
    return $sub_result->execute()
      ->fetchAllKeyed(0, 1);
  }

}
