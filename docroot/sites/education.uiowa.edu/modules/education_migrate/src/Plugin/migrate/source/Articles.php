<?php

namespace Drupal\education_migrate\Plugin\migrate\source;

use Drupal\Component\Utility\Html;
use Drupal\migrate\Row;
use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;
use Drupal\sitenow_migrate\Plugin\migrate\source\ProcessMediaTrait;
use Drupal\sitenow_migrate\Plugin\migrate\source\LinkReplaceTrait;
use Drupal\migrate\Event\MigrateImportEvent;
use Drupal\taxonomy\Entity\Term;

/**
 * Migrate Source plugin.
 *
 * @MigrateSource(
 *   id = "education_articles",
 *   source_module = "node"
 * )
 */
class Articles extends BaseNodeSource {
  use ProcessMediaTrait;
  use LinkReplaceTrait;

  /**
   * Term-to-term mapping for tags.
   *
   * @var array
   */
  protected $termMapping;

  /**
   * Node-to-term mapping for affiliations.
   *
   * @var array
   */
  protected $affiliationMapping;

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();
    // Make sure our nodes are retrieved in order,
    // and force a highwater mark of our last-most migrated node.
    $query->orderBy('nid');
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

    // Process the image field.
    $image = $row->getSourceProperty('field_image');
    if (!empty($image)) {
      $mid = $this->processImageField($image[0]['fid'], $image[0]['alt'], $image[0]['title']);
      $row->setSourceProperty('field_image_mid', $mid);
    }

    $this->getTags($row);
    $this->getAffiliations($row);

    $body = $row->getSourceProperty('body');

    if (!empty($body)) {
      // Search for D7 inline embeds and replace with D8 inline entities.
      $body[0]['value'] = $this->replaceInlineFiles($body[0]['value']);

      // Extract the summary.
      $row->setSourceProperty('body_summary', $this->getSummaryFromTextField($body));

      // Parse links.
      $doc = Html::load($body[0]['value']);
      $links = $doc->getElementsByTagName('a');
      $i = $links->length - 1;

      while ($i >= 0) {
        $link = $links->item($i);
        $href = $link->getAttribute('href');

        if (strpos($href, '/node/') === 0 || stristr($href, 'education.uiowa.edu/node/')) {
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

        $i--;
      }

      // Grab all the paragraph tags. Check if there's a pull-quote attribute.
      $paragraphs = $doc->getElementsByTagName('p');
      $i = $paragraphs->length - 1;
      while ($i >= 0) {
        $paragraph = $paragraphs->item($i);
        $classes = $paragraph->getAttribute('class');
        // If it is a pull-quote, then convert it to a blockquote.
        if (str_contains($classes, 'pull-quote')) {
          // We either need to fetch the child nodes into an array first,
          // and then traverse them, or traverse in reverse.
          // Otherwise, once we import the node,
          // We'll lose the reference in $paragraph->childNodes.
          $child_nodes = [];
          foreach ($paragraph->childNodes as $child) {
            $child_nodes[] = $child;
          }
          // Create an empty blockquote element.
          $blockquote = $doc->createElement('blockquote');
          // Copy and append each of our fetched children nodes.
          foreach ($child_nodes as $child) {
            $new_child = $paragraph->ownerDocument->importNode($child, TRUE);
            $blockquote->appendChild($new_child);
          }
          // Replace the paragraph with the new blockquote.
          $paragraph->parentNode->replaceChild($blockquote, $paragraph);
        }

        $i--;
      }

      // Grab all the div tags. Check if they have the pull-quote attribute.
      $divs = $doc->getElementsByTagName('div');
      $i = $divs->length - 1;
      while ($i >= 0) {
        $div = $divs->item($i);
        $classes = $div->getAttribute('class');

        if (str_contains($classes, 'pull-quote')) {
          $child_nodes = [];
          // Loop through the children to grab the image and its caption.
          foreach ($div->childNodes as $child) {
            $child_nodes[$child->nodeName] = $child;
          }
          // Update the D8 entity embed code to align left and include
          // the caption.
          if (isset($child_nodes['drupal-media']) && isset($child_nodes['p'])) {
            $child_nodes['drupal-media']->setAttribute('data-align', 'left');
            $child_nodes['drupal-media']->setAttribute('data-caption', $child_nodes['p']->nodeValue);
          }
          // Replace the div with the newly updated media entity.
          $div->parentNode->replaceChild($child_nodes['drupal-media'], $div);
        }

        $i--;
      }

      $html = Html::serialize($doc);
      $body[0]['value'] = $html;

      // Take the short description and prepend it to the body.
      $short_description = $row->getSourceProperty('field_header_short_description');
      if ($short_description) {
        $short_description = '<p class="uids-component--light-intro">' . $short_description[0]['value'] . '</p>';
        $body[0]['value'] = $short_description . $body[0]['value'];
      }
      // Take the body and prepend the byline.
      $byline = $row->getSourceProperty('field_article_byline');
      if ($byline) {
        $byline = '<p>' . $byline[0]['value'] . '</p>';
        $body[0]['value'] = $byline . $body[0]['value'];
      }
      // Replace "btn-primary", "btn-long" with "bttn bttn--caps bttn--primary".
      $body[0]['value'] = str_replace('btn-primary', 'bttn bttn--caps bttn--primary', $body[0]['value']);
      $body[0]['value'] = str_replace('btn-long', 'bttn bttn--caps bttn--primary', $body[0]['value']);
      // Add in the missing blockquote class.
      $body[0]['value'] = str_replace('<blockquote>', '<blockquote class="blockquote">', $body[0]['value']);

      // Set the body format.
      $body[0]['format'] = 'filtered_html';

      $row->setSourceProperty('body', $body);
    }

    // Process the image field.
    $image = $row->getSourceProperty('field_image');
    if (!empty($image)) {
      $mid = $this->processImageField($image[0]['fid'], $image[0]['alt'], $image[0]['title']);
      $row->setSourceProperty('field_image_mid', $mid);
    }

    // D7 image caption used filtered_html with up to 3 rows of characters.
    // We need to truncate and strip tags,
    // using the summary extraction function.
    $image_caption = $row->getSourceProperty('field_article_image_caption');
    if ($image_caption) {
      $image_caption = $this->extractSummaryFromText($image_caption[0]['value'], 252);
      $row->setSourceProperty('field_article_image_caption', $image_caption);
    }

    // If we have a linked source, split it up into our 2 separate fields.
    $source = $row->getSourceProperty('field_article_source');
    if ($source) {
      $row->setSourceProperty('source_org', $source[0]['title']);
      // Let's try and fix some http:// to https:// while we're at it.
      $row->setSourceProperty('source_url', str_replace('http://', 'https://', $source[0]['url']));
    }

    $this->clearMemory();
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function postImport(MigrateImportEvent $event) {
    $this->reportPossibleLinkBreaks(['node__body' => ['body_value']]);
  }

  /**
   * Map taxonomy to a tag.
   */
  protected function getTags(&$row) {
    $tables = [
      'field_data_field_tags' => ['field_tags_tid'],
    ];
    $this->fetchAdditionalFields($row, $tables);
    $tids = $row->getSourceProperty('field_tags');
    if (empty($tids)) {
      return;
    }
    // Check if we've already found a mapping for each term.
    foreach ($tids as $tid) {
      $id = $tid['tid'];
      if (isset($this->termMapping[$id])) {
        $new_tids[] = $this->termMapping[$id];
      }
      else {
        $source_tids[] = $id;
      }
    }
    // If we have unmigrated source terms, create new.
    if (!empty($source_tids)) {
      $source_query = $this->select('taxonomy_term_data', 't');
      $source_query = $source_query->fields('t', [
        'tid',
        'name',
        'description',
      ])
        ->condition('t.tid', $source_tids, 'in');
      $terms = $source_query->distinct()
        ->execute()
        ->fetchAllAssoc('tid');
      foreach ($terms as $tid => $details) {
        // Attempt to query the new database with the name
        // to see if we've already created it.
        $dest_tid = \Drupal::database()->select('taxonomy_term_field_data', 't')
          ->fields('t', ['tid'])
          ->condition('t.name', $details['name'], '=')
          ->execute()
          ->fetchCol();
        // If found, add to new_tids and break out.
        if (!empty($dest_tid)) {
          $this->termMapping[$tid] = $dest_tid[0];
          $new_tids[] = $dest_tid[0];
          continue;
        }
        // We didn't find a previously created term,
        // so we're making it now.
        $new_term = Term::create([
          'name' => $details['name'],
          'vid' => 'tags',
          'description' => $details['description'],
        ]);
        if ($new_term->save()) {
          $this->termMapping[$tid] = $new_term->id();
          $new_tids[] = $new_term->id();
        }
      }
    }

    // And, if we have any existing or newly created terms,
    // add them back to the field.
    if (!empty($new_tids)) {
      $row->setSourceProperty('article_tids', $new_tids);
    }
  }

  /**
   * Map affiliations to a tag.
   */
  protected function getAffiliations(&$row) {
    $tables = [
      'field_data_field_article_affiliation' => ['field_article_affiliation_target_id'],
    ];
    $this->fetchAdditionalFields($row, $tables);
    $tids = $row->getSourceProperty('field_article_affiliation');
    if (empty($tids)) {
      return;
    }
    // Check if we've already found a mapping for each term.
    foreach ($tids as $tid) {
      $id = $tid['target_id'];
      if (isset($this->affiliationMapping[$id])) {
        $new_tids[] = $this->affiliationMapping[$id];
      }
      else {
        $source_tids[] = $id;
      }
    }
    // If we have unmigrated source affiliations, create new.
    if (!empty($source_tids)) {
      $source_query = $this->select('node', 'n');
      $source_query = $source_query->fields('n', [
        'nid',
        'title',
      ])
        ->condition('n.nid', $source_tids, 'in');
      $terms = $source_query->distinct()
        ->execute()
        ->fetchAllKeyed(0, 1);
      foreach ($terms as $nid => $title) {
        // Attempt to query the new database with the name
        // to see if we've already created it.
        $dest_tid = \Drupal::database()->select('taxonomy_term_field_data', 't')
          ->fields('t', ['tid'])
          ->condition('t.name', $title, '=')
          ->execute()
          ->fetchCol();
        // If found, add to new_tids and break out.
        if (!empty($dest_tid)) {
          $this->affiliationMapping[$nid] = $dest_tid[0];
          $new_tids[] = $dest_tid[0];
          continue;
        }
        // We didn't find a previously created term,
        // so we're making it now.
        $new_term = Term::create([
          'name' => $title,
          'vid' => 'tags',
          'description' => '',
        ]);
        if ($new_term->save()) {
          $this->affiliationMapping[$nid] = $new_term->id();
          $new_tids[] = $new_term->id();
        }
      }
    }

    // And, if we have any existing or newly created terms,
    // add them back to the field.
    if (!empty($new_tids)) {
      $row->setSourceProperty('article_tids', array_merge((array) $row->getSourceProperty('article_tids'), $new_tids));
    }
  }

}
