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
          $site_path = \str_replace('sites/', '', \Drupal::service('site.path'));
          if (strpos($href, '/node/') === 0 || stristr($href, $site_path . '/node/')) {
            $nid = explode('node/', $href)[1];

            if ($lookup = $this->manualLookup($nid)) {
              $link->setAttribute('href', '/node/' . $lookup);
              $link->parentNode->replaceChild($link, $link);
              $this->logger->info('Replaced internal link @link in article @article.', [
                '@link' => $href,
                '@article' => $row->getSourceProperty('title'),
              ]);

            }
            else {
              $this->logger->notice('Unable to replace internal link @link in article @article.', [
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
    $this->logger->notice('Beginning link replace.');
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

        $this->logger->info('Checking @link in entity @entity.', [
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
            $site_path = \str_replace('sites/', '', \Drupal::service('site.path'));
            if (strpos($href, '/node/') === 0 || stristr($href, $site_path . '/node/')) {
              $nid = explode('node/', $href)[1];

              if ($lookup = $this->manualLookup($nid)) {
                $link->setAttribute('href', '/node/' . $lookup);
                $link->parentNode->replaceChild($link, $link);
                $this->logger->info('Replaced internal link from /node/@nid to /node/@link in entity @entity.', [
                  '@nid' => $nid,
                  '@link' => $lookup,
                  '@entity' => $entity_id,
                ]);
              }
              else {
                $this->logger->notice('Unable to replace internal link @link in entity @entity.', [
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
   */
  private function reportPossibleLinkBreaks(array $fields) {
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

          // Checks for any links using the node/ format
          // and reports the node id on which it was found
          // and the linked text.
          if (preg_match_all('|<a.*?node.*?>(.*?)<\/a>|i', $value, $matches)) {
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
   * Override for manual lookup tables of pre-migrated content.
   */
  private function manualLookup(int $nid) {
    return FALSE;
  }

}
