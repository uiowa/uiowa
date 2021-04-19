<?php

namespace Drupal\sitenow_migrate\Plugin\migrate\source;

use Drupal\Component\Utility\Html;
use Drupal\migrate\Plugin\MigrationInterface;
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
              $link->setAttribute('href', $lookup);
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
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The migration object.
   * @param string $field_name
   *   The field name which should receive post-migration link processing.
   */
  private function postLinkReplace(MigrationInterface $migration, string $field_name) {
    // @todo account for the possibility of multiple migrations.
    $mapping = $migration->getIdMap();
    $destination_ids = $migration->getDestinationIds();
    $nodes = \Drupal::service('entity_type.manager')
      ->getStorage('node')
      ->loadMultiple($destination_ids);
    foreach ($nodes as $node) {
      $field = $node->get($field_name);
      if (!empty($field)) {
        // Search for D7 inline embeds and replace with D8 inline entities.
        $field[0]['value'] = $this->replaceInlineFiles($field[0]['value']);
        // Parse links.
        $doc = Html::load($field[0]['value']);
        $links = $doc->getElementsByTagName('a');
        $i = $links->length - 1;
        while ($i >= 0) {
          $link = $links->item($i);
          $href = $link->getAttribute('href');
          $site_path = \str_replace('sites/', '', \Drupal::service('site.path'));
          if (strpos($href, '/node/') === 0 || stristr($href, $site_path . '/node/')) {
            $nid = explode('node/', $href)[1];

            if ($lookup = $this->manualLookup($nid) ||
              $lookup = $mapping->lookupSourceId(['nid' => $nid])) {
              $link->setAttribute('href', $lookup);
              $link->parentNode->replaceChild($link, $link);
              $this->logger->info('Replaced internal link @link in article @article.', [
                '@link' => $href,
                '@article' => $node->get('title'),
              ]);
            }
            else {
              $this->logger->notice('Unable to replace internal link @link in article @article.', [
                '@link' => $href,
                '@article' => $node->getSourceProperty('title'),
              ]);
            }
          }

          $i--;
        }
      }

      $html = Html::serialize($doc);
      $field[0]['value'] = $html;

      $$node->set($field_name, $field);
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
   * Override for manual lookup tables of pre-migrated content.
   */
  private function manualLookup(int $nid) {
    return FALSE;
  }

}
