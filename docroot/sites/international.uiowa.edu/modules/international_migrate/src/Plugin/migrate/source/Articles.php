<?php

namespace Drupal\international_migrate\Plugin\migrate\source;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\layout_builder\Section;
use Drupal\migrate\Event\MigrateImportEvent;
use Drupal\migrate\Row;
use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;
use Drupal\sitenow_migrate\Plugin\migrate\source\LinkReplaceTrait;
use Drupal\sitenow_migrate\Plugin\migrate\source\ProcessMediaTrait;
use Drupal\taxonomy\Entity\Term;

/**
 * Migrate Source plugin.
 *
 * @MigrateSource(
 *   id = "international_articles",
 *   source_module = "node"
 * )
 */
class Articles extends BaseNodeSource {
  use ProcessMediaTrait;
  use LinkReplaceTrait;

  /**
   * Term-to-name mapping for authors.
   *
   * @var array
   */
  protected $termMapping;

  /**
   * Tag-to-name mapping for keywords.
   *
   * @var array
   */
  protected $tagMapping;

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();
    // Only import news newer than January 2015.
    $query->condition('created', strtotime('2015-01-01'), '>=');
    $query->leftJoin('url_alias', 'alias', "alias.source = CONCAT('node/', n.nid)");
    $query->fields('alias', ['alias']);
    // Make sure our nodes are retrieved in order,
    // and force a highwater mark of our last-most migrated node.
    $query->orderBy('nid');
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = parent::fields();
    $fields['alias'] = $this->t('The URL alias for this node.');
    return $fields;
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

    // Get the author tags to build into our mapped
    // field_news_authors value.
    $tables = [
      'field_data_field_news_author' => ['field_news_author_tid'],
      'field_data_field_news_tags' => ['field_news_tags_tid'],
    ];
    $this->fetchAdditionalFields($row, $tables);
    $author_tids = $row->getSourceProperty('field_news_author_tid');
    if (!empty($author_tids)) {
      $authors = [];
      foreach ($author_tids as $tid) {
        if (!isset($this->termMapping[$tid])) {
          $source_query = $this->select('taxonomy_term_data', 't');
          $source_query = $source_query->fields('t', ['name'])
            ->condition('t.tid', $tid, '=');
          $this->termMapping[$tid] = $source_query->execute()->fetchCol()[0];
        }
        $authors[] = $this->termMapping[$tid];
      }
      $source_org_text = implode(', ', $authors);
      $row->setSourceProperty('field_news_authors', $source_org_text);
    }

    $body = $row->getSourceProperty('body');

    if (!empty($body)) {
      $this->viewMode = 'medium__no_crop';
      $this->align = 'left';
      // Search for D7 inline embeds and replace with D8 inline entities.
      $body[0]['value'] = $this->replaceInlineFiles($body[0]['value']);

      // Clean up extra wrapper divs.
      $doc = Html::load($body[0]['value']);
      $divs = $doc->getElementsByTagName('div');
      $i = $divs->length - 1;
      while ($i >= 0) {
        $div = $divs->item($i);
        $classes = $div->getAttribute('class');
        // Div classes we're interested in are in the form of
        // image-alignment-size.
        if (str_contains($classes, 'image-')) {
          preg_match('|image-(.*?)-(.*?)|i', $classes, $match);
          // Pull out the alignment attribute.
          $align = $match[1];
          $children = [];
          foreach ($div->childNodes as $child) {
            if ($child->nodeName === 'drupal-media') {
              // Set the old alignment on the newly created
              // D8 media entity embeds.
              $child->setAttribute('data-align', $align);
            }
            $children[] = $child;
          }
          // Set any of the div's children on the parent node,
          // in the place where the wrapper div sits currently.
          foreach ($children as $child) {
            $div->parentNode->insertBefore($child, $div);
          }
          // Now that all the children have been set,
          // remove the no longer needed wrapper div.
          $div->parentNode->removeChild($div);
        }
        $i--;
      }
      // Parse links.
      $links = $doc->getElementsByTagName('a');
      $i = $links->length - 1;
      $inv_delta = 0;
      $nid = $row->getSourceProperty('nid');
      while ($i >= 0) {
        $link = $links->item($i);
        $href = $link->getAttribute('href');
        if (strpos($href, '/node/') === 0 || stristr($href, 'international.uiowa.edu/node/')) {
          $inv_delta++;
          if ($lookup = $this->manualLookup($nid, $inv_delta)) {
            // If we get a -1, then we should remove the link
            // and replace it just with its text.
            if ($lookup === -1) {
              $text = $doc->createTextNode($link->nodeValue);
              $link->parentNode->replaceChild($text, $link);
            }
            // Else if we have a string, replace it with
            // the lookup URL directly.
            elseif (is_string($lookup)) {
              $link->setAttribute('href', $lookup);
              $link->parentNode->replaceChild($link, $link);
            }
            // Lastly, if we have an int that wasn't -1,
            // then recreate the node/# format.
            else {
              $link->setAttribute('href', '/node/' . $lookup);
              $link->parentNode->replaceChild($link, $link);
            }
          }
          else {
            $this->logger->notice('Unable to replace internal link @link in article @article, node @nid.', [
              '@link' => $href,
              '@article' => $row->getSourceProperty('title'),
              '@nid' => $nid,
            ]);
          }
        }
        $i--;
      }
      // Re-serialize the DOM and set into the body text.
      $body[0]['value'] = Html::serialize($doc);
      // Set the format to filtered_html while we have it.
      $body[0]['format'] = 'filtered_html';

      $row->setSourceProperty('body', $body);

      // Extract the summary.
      $row->setSourceProperty('body_summary', $this->getSummaryFromTextField($body));
    }

    // Set our tagMapping if it's not already.
    if (empty($this->tagMapping)) {
      $this->tagMapping = \Drupal::database()
        ->select('taxonomy_term_field_data', 't')
        ->fields('t', ['name', 'tid'])
        ->condition('t.vid', 'tags', '=')
        ->execute()
        ->fetchAllKeyed();
    }

    $tag_tids = $row->getSourceProperty('field_news_tags_tid');
    if (!empty($tag_tids)) {
      // Fetch tag names based on TIDs from our old site.
      $tag_results = $this->select('taxonomy_term_data', 't')
        ->fields('t', ['name'])
        ->condition('t.tid', $tag_tids, 'IN')
        ->execute();
      $tags = [];
      foreach ($tag_results as $result) {
        $tag_name = $result['name'];
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

        // Add the mapped TID to match our tag name.
        $tags[] = $this->tagMapping[$tag_name];

      }
      $row->setSourceProperty('tags', $tags);
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function postImport(MigrateImportEvent $event) {
    // If we haven't finished our migration, or
    // if we're doing the redirects migration,
    // don't proceed with the following.
    $migration = $event->getMigration();
    if (!$migration->allRowsProcessed() || $migration->id() === 'international_article_redirects') {
      return;
    }
    // Node ids to be updated, as well as section number.
    // They all are currently section 2, but this may need
    // to be updated prior to prod migration.
    $to_update = [
      1376 => 2,
      1391 => 2,
      1396 => 2,
      1411 => 2,
    ];
    if ($this->replaceSpecifiedLinks($to_update)) {
      $this->logger->notice('Preexisting node links updated: @nids', [
        '@nids' => implode(', ', array_keys($to_update)),
      ]);
    }
    else {
      $this->logger->notice('Unable to update node links: @nids', [
        '@nids' => implode(', ', array_keys($to_update)),
      ]);
    }
    // Report possible broken links after our known high water mark
    // of articles in which we fixed links.
    $this->reportPossibleLinkBreaks(['node__body' => ['body_value']], 11901);
  }

  /**
   * Replace links for several specified nodes.
   */
  private function replaceSpecifiedLinks($to_update) {
    $db = \Drupal::database();
    if (!$db->schema()->tableExists('migrate_map_international_articles')) {
      return FALSE;
    }
    $map = $db->select('migrate_map_international_articles', 'm')
      ->fields('m', ['sourceid1', 'destid1'])
      ->execute()
      ->fetchAllKeyed();

    /** @var \Drupal\Core\Entity\EntityStorageInterface $entity_manager */
    $entity_manager = \Drupal::service('entity_type.manager')
      ->getStorage('node');

    /** @var \Drupal\Core\Entity\EntityStorageInterface $block_manager */
    $block_manager = \Drupal::service('entity_type.manager')
      ->getStorage('block_content');

    $nids = array_keys($to_update);
    $nodes = $entity_manager->loadMultiple($nids);
    foreach ($nodes as $node) {
      // We should be able to index the to_update array
      // to get the correct section delta. If we can't,
      // then something has gone seriously wrong. Time to
      // quit and spit out an error notice.
      if (!isset($to_update[$node->id()])) {
        return FALSE;
      }
      // Otherwise, if it's set, then grab the section delta
      // we need to update.
      $section_delta = $to_update[$node->id()];
      // Grab our section from the node's layout. Here we know
      // the structure of our pages, so we can grab it directly.
      $layout = $node->get('layout_builder__layout');
      $section = $layout->getSection($section_delta);
      $section_array = $section->toArray();
      $uuid = array_keys($section_array['components'])[0];
      $component = $section_array['components'][$uuid];
      $revision_id = $component['configuration']['block_revision_id'];

      if ($block_manager instanceof RevisionableStorageInterface && $block = $block_manager->loadRevision($revision_id)) {
        $block_text = $block->field_uiowa_text_area->value;

        $doc = Html::load($block_text);
        // Parse links.
        $links = $doc->getElementsByTagName('a');
        $i = $links->length - 1;
        while ($i >= 0) {
          $link = $links->item($i);
          $href = $link->getAttribute('href');
          if (strpos($href, '/node/') === 0 || stristr($href, 'international.uiowa.edu/node/')) {
            $nid = explode('node/', $href)[1];
            if ($lookup = $map[$nid]) {
              $link->setAttribute('href', '/node/' . $lookup);
              $link->parentNode->replaceChild($link, $link);
            }
            else {
              $text = $doc->createTextNode($link->nodeValue);
              $link->parentNode->replaceChild($text, $link);
            }
          }
          $i--;
        }
        // Re-serialize the DOM and set into the body text.
        $block_text = Html::serialize($doc);
        $block->field_uiowa_text_area->value = $block_text;
        $block->save();
        // Set the new revision in the component
        // and place it back into the section array.
        $component['configuration']['block_revision_id'] = $block->getRevisionId();
        $section_array['components'][$uuid] = $component;
        // Remove the old section and append our new one.
        $layout->removeSection($section_delta);
        $layout->appendSection(Section::fromArray($section_array));
        // Place the new layout back into the node field and save.
        $node->set('layout_builder__layout', $layout->getSections());
        $node->setNewRevision(TRUE);
        $node->revision_log = 'Auto-updated links during news migration.';
        $node->save();
      }
    }
    return TRUE;
  }

  /**
   * Return the destination given a NID on the old site.
   *
   * @param int $nid
   *   The source node ID.
   * @param int $inv_delta
   *   Delta denoting which link to grab.
   *
   * @return false|string|int
   *   The new node id, path, or FALSE if not in the map.
   */
  protected function manualLookup(int $nid, int $inv_delta) {
    // Depending on when the migration is run in production,
    // we may need to offset some of the node ids to account
    // for their new order in node id.
    $offset = 0;
    $article_start = 3121;
    $map = [
      3850 => [1071],
      4117 => [-1],
      4121 => [2001],
      4127 => [576],
      4130 => [-1],
      4174 => [-1],
      4179 => [781],
      4188 => [-1],
      4190 => [-1],
      4203 => [2491],
      4208 => [1061],
      4233 => [2001],
      4240 => [1061],
      4270 => [66],
      4272 => [66],
      4278 => [1061],
      4283 => [576, -1],
      4288 => [-1, 16],
      4293 => [576],
      4294 => [-1],
      4296 => [781],
      4299 => [576],
      4300 => [576],
      4301 => [781],
      4307 => [981],
      4321 => [
        1981,
        1386,
        1351,
        681,
      ],
      4338 => [1106],
      4339 => [1106],
      4342 => [206],
      4343 => [-1],
      4346 => [576],
      4349 => [2491],
      4351 => [-1],
      4358 => [-1],
      4383 => [-1],
      4389 => [2491],
      4392 => [981],
      4395 => [
        1376,
        2036,
      ],
      4405 => [
        1376,
        2036,
      ],
      4412 => [
        1376,
        2036,
      ],
      4415 => [
        1376,
        2036,
      ],
      4417 => [
        1376,
        2036,
      ],
      4418 => [
        1376,
        2036,
      ],
      4419 => [781],
      4447 => [-1],
      4451 => [781],
      4462 => [-1],
      4463 => [
        1376,
        2036,
      ],
      4464 => [2001, 1376, 2036],
      4465 => [3206, -1, -1, -1],
      4473 => [1061],
      4481 => [206],
      4487 => [2001, 2026, 1376, 2036],
      4488 => [1376, 2036],
      4493 => [1376, 1376, 31, 896],
      4498 => [981],
      4500 => [
        786,
        2491,
        'https://www.press-citizen.com/story/opinion/contributors/writers-group/2015/04/25/obama-tell-gulf-leaders/26321961/',
        'https://www.press-citizen.com/story/opinion/contributors/guest-editorials/2015/03/19/new-media-social-change-middle-east/25022323/',
        'https://now.uiowa.edu/2015/04/learning-about-arab-spring-global-context',
      ],
      4502 => [206],
      4504 => [781],
      4517 => [2001, -1, -1],
      4521 => [-1],
      4523 => [21],
      4524 => [576, 576, 576],
      4527 => [-1],
      4533 => [-1],
      4534 => [2001, 2001, 2001, 2591],
      4535 => [576, 576, 576],
      4536 => [2001],
      4541 => [-1],
      4546 => [-1],
      4556 => [-1],
      4629 => [1841],
      4631 => [-1],
      4647 => [576, 576],
      4664 => [756],
      4666 => [1016, 1031],
      4694 => [576],
      4703 => [1061, 781],
      4716 => [-1],
      4721 => [
        'https://exchange.prx.org/search/pieces?q=worldcanvass%3A+don+quixote&commit=Search',
        -1,
        -1,
      ],
      4728 => [981, 996],
      4734 => [2001, 2001, 2591],
      4735 => [756],
      4737 => [1071],
      4741 => [781, 781],
      4773 => [2001, 2001, 2001, 2591],
      4775 => [756],
      4776 => [-1, -1],
      4777 => [-1, 781, 966, 781],
      4778 => [781, -1, -1],
      4822 => [-1, 2036, 2031, 876],
      4826 => [756],
      4827 => [636, -1],
      4831 => [-1],
      4832 => [1016, 1031],
      4836 => [
        'https://globalhealthstudies.uiowa.edu/',
        -1,
      ],
      4837 => [2026],
      4844 => [756],
      4853 => [1061, -1, -1],
      4861 => [2001, 2001, -1, 2591],
      4862 => [2001],
      4870 => [1031],
      4876 => [-1, 956],
      4877 => [-1, 956],
      4878 => [-1, 956],
      4881 => [-1],
      4882 => [1061, 781, 781, -1],
      4883 => [1531],
      4885 => [-1, 956],
      4886 => [-1, 956],
      4887 => [-1, 956],
      4889 => [21, -1, 81, 16],
      4896 => [-1, 966, -1],
      4903 => [-1, -1, -1, -1],
      4921 => [-1],
      4930 => [756],
      4931 => [1061],
      5098 => [-1],
      5099 => [-1],
      5104 => [2036],
      5106 => [16],
      5110 => [1061, -1],
      5112 => [-1],
      5122 => [1061, 781, -1],
      5123 => [756],
      5132 => [-1],
      5145 => [781],
      5176 => [1061, 781, -1],
      5180 => [1061, -1],
      5189 => [856],
      5203 => [781],
      5205 => [-1],
      5213 => [1106],
      5240 => [1061, 781, -1],
      5241 => [-1, -1],
      5259 => [981, 996],
      5272 => [756],
      5276 => [1016],
      5284 => [781],
      5285 => [1061],
      5286 => [1061],
      5289 => [1016],
      5290 => [756],
      5309 => [621],
      5311 => [1531],
      5312 => [2036],
      5324 => [756],
      5326 => [1071],
      5327 => [1071],
      5328 => [-1],
      5332 => [1071],
      5338 => [781, -1],
      5343 => [996],
      5347 => [-1],
      5352 => [576],
      5365 => [1061],
      5373 => [2],
      5374 => [756],
      5376 => [-1],
      5377 => [2036],
      5378 => [2036],
      5379 => [2036],
      5380 => [2036],
      5381 => [2036],
      5382 => [2036],
      5383 => [2036],
      5394 => [1061],
      5400 => [-1],
      5401 => [2036],
      5407 => [2031],
      5408 => [2036],
      5409 => [2001, 2036],
      5426 => [-1],
      5429 => [-1],
      5430 => [751, 751, 636],
      5499 => [576],
      5526 => [781, -1],
      5561 => [-1],
      5566 => [-1],
      5611 => [2526],
      5701 => [876],
      5746 => [-1, 1481],
      5891 => [1061, -1, -1],
      5896 => [-1],
      5946 => [306],
      6386 => [-1],
      6431 => [981],
      6481 => [206],
      6561 => [-1, 956],
      6571 => [756, -1],
      6581 => [-1],
      6666 => [576],
      6671 => [576],
      6717 => [206],
      6736 => [-1, 956],
      6746 => [1096],
      6756 => [-1, 956],
      6766 => [-1, 956],
      6771 => [966],
      6801 => [2],
      6811 => [-1, 956],
      6816 => [-1, 956],
      6821 => [-1, 956],
      6941 => [1061, 2496],
      7006 => [-1],
      7021 => [-1],
      7081 => ['https://studyabroad.sit.edu/'],
      7086 => ['https://fundforeducationabroad.org/', 751],
      7096 => [-1],
      7111 => [1061, 'https://www.press-citizen.com/'],
      7161 => [781],
      7231 => [1061, 'https://www.press-citizen.com/'],
      7241 => [-1],
      7246 => [2036],
      7306 => [751],
      7426 => [-1, 956],
      7476 => [-1, -1],
      7491 => [781],
      7626 => [1061, 'https://www.press-citizen.com/'],
      7686 => [1096],
      7691 => [21],
      7756 => [1521, 2],
      7921 => [1521, 2, 1521],
      7951 => [576],
      7961 => [1096],
      7991 => ['https://www.press-citizen.com/'],
      8026 => [1671],
      8036 => [996],
      8096 => [21],
      8121 => [786, 786],
      8251 => [996],
      8256 => [996],
      8266 => ['https://www.press-citizen.com/', 'https://dailyiowan.com/'],
      8281 => [781],
      8331 => [-1],
      8336 => [1096],
      8356 => [31, 2036],
      8421 => [451, -1],
      8526 => [1306],
      8551 => [-1],
      8576 => [786, 'https://www.press-citizen.com/'],
      8596 => [751],
      8936 => [751],
      8956 => [-1, -1],
      8961 => [-1],
      8971 => [876],
      9021 => [781],
      9136 => [996],
      9171 => [1521, 2],
      9191 => [781, 1061, 'https://www.press-citizen.com/'],
      9321 => [781, 1061, 'https://www.press-citizen.com/'],
      9396 => [
        1061,
        'https://www.press-citizen.com/',
        'https://dailyiowan.com/',
      ],
      9511 => [7146],
      9531 => [856],
      9541 => [751],
      9596 => [-1],
      9626 => [1016],
      9886 => [7471],
      9901 => [1061],
      9906 => [1521, 2],
      10026 => [1061, 'https://www.press-citizen.com/'],
      10156 => [
        1061,
        'https://www.press-citizen.com/',
        'https://dailyiowan.com/',
      ],
      10256 => [7471],
      10401 => [-1],
      10426 => [-1],
      10431 => [751],
      10501 => [7506, 16],
      10641 => [1106],
      10646 => [-1],
      10681 => [996],
      10701 => [1016],
      10756 => [2501],
      10806 => [781],
      10916 => [781, 966, 781],
      10921 => [971, 1071],
      10931 => [1061, 'https://www.press-citizen.com/'],
      10936 => [-1],
      10951 => [2001],
      10956 => [206],
      10966 => [1016],
      10996 => [756],
      11001 => [206],
      11021 => [-1],
      11031 => [781],
      11051 => ['https://www.press-citizen.com/'],
      11141 => [1061, 'https://www.press-citizen.com/'],
      11146 => [751, 751],
      11266 => [781],
      11276 => [966],
      11281 => [981],
      11286 => [1521, 2],
      11351 => [981],
      11356 => [751, 16],
      11396 => [1521, 1521, 2],
      11411 => [1016],
      11446 => [1061, 'https://www.press-citizen.com/'],
      11486 => [826, 826],
      11581 => [791, 2596],
      11596 => [1096],
      11606 => [781, 2596],
      11621 => [786, 2776, 781],
      11626 => [1671, 2776, 2776],
      11631 => [2776, 781, 1671, 2776],
      11661 => [2786, 2786],
      11676 => [1671, 2776, 2776],
      11721 => [1016],
      11736 => [1306],
      11746 => [8691, 8671, 8681, 8676, 896],
      11756 => [781, 2596],
      11761 => [-1],
      11766 => [8486, 5056],
      11931 => [7746],
      11966 => [756],
      11986 => [781, 2776],
      12016 => [756],
      12081 => [2026, 8691],
      12156 => [9181, 9116],
      12251 => [2036, 2001, 876],
      12291 => [2036, 8651],
      12296 => [751],
      12356 => [9221, 9216],
      12366 => [2001],
      12371 => [781],
      12386 => [9126],
      12416 => [1106],
      12451 => [1521],
      12521 => [1061],
      12581 => [966, 781],
      12621 => [171, 171],
      12701 => [1061],
      12766 => ['https://korn.uiowa.edu'],
      12771 => [966, 1061],
      12786 => [2506],
      12921 => [781],
      13236 => [1061, 'https://www.press-citizen.com/'],
      13286 => [
        1061,
        'https://dailyiowan.com/2020/02/23/university-of-iowa-panel-discusses-response-concerns-on-coronavirus/',
      ],
      13296 => [751],
      13436 => [10036],
      13451 => [10026],
      13496 => [2001],
      13596 => [16, 21],
      13746 => [8651],
      13856 => [-1, 161, 1381],
      13866 => [-1],
      13916 => [2001, 2001, 2001],
      13931 => [161],
      13971 => [191],
      13976 => [-1],
      13996 => [2036, 2001, 876],
      14006 => [751],
      14016 => [-1],
      14021 => [576],
      14041 => [-1, 616, 191],
      14076 => [966, 961, 956],
      14091 => [616, 751],
      14096 => [956, 966, 10551],
      14106 => [966, 956, 1061],
      14111 => [2536, 781, 956, 10556, 10561],
      14116 => [2536],
      14121 => [2536],
      14131 => [1386, 1521],
      14146 => [-1],
      14151 => [10571, 2791],
      14156 => [1521],
      14226 => [966, 1061, 956, 2536],
      14251 => [1061, 1521, 956, 961, 616],
      14276 => [1606],
      14286 => [2801],
      14326 => [-1],
      14346 => [616],
      14371 => [2821],
      14381 => [781],
      14401 => [-1, 10741, 2806, 2821],
      14446 => [571, 751, 576, 636],
      14471 => [781],
      14526 => [
        10741,
        2806,
        -1,
        'https://now.uiowa.edu/2021/02/ui-named-top-producer-fulbright-students-sixth-consecutive-year',
      ],
      14541 => [2806],
      14596 => [781],
      14616 => [2811],
      14651 => [-1, 2811, 2816],
      14686 => [1386],
      14706 => [1071],
      14761 => [1306],
      14821 => [11081, 11071],
      14891 => [1986],
      14951 => [10751],
      15031 => [616],
      15056 => [806, 11281],
      15076 => [2006],
      15086 => [11171],
      15106 => [11356],
      15156 => [776],
      15171 => [-1],
      15186 => [1986, 11221, 10301, 10266, 9581, 1986, 21],
      15211 => [2006, 781, 2006],
      15251 => [776],
      15256 => [776],
      15261 => [-1],
      15281 => [2006],
      15291 => [1521],
      15346 => [2001],
      15351 => [9221, 9226],
      15361 => [11566],
      15376 => [2536, 2531, 956, 2826, 2831],
      15396 => [1986],
      15456 => [1071],
      15471 => ['https://www.arabnews.com/node/1964091/business-economy'],
      15496 => [11631],
      15556 => [956, 2531, 11511],
      15581 => [11861],
      15596 => [2001, 1836],
      15606 => [2001, 1986],
      15636 => [11631, 11666, 11731, 11746],
      15646 => [1666, 1671],
      15651 => [2036],
      15711 => [751],
      15716 => [781, 1666],
      15731 => [791],
      15741 => [11731],
      15796 => [1986, 1356],
    ];
    if (!isset($map[$nid])) {
      return FALSE;
    }
    $available_links = $map[$nid];
    // Set a floor at 0. Some, especially deletions, are
    // repeated but not reflected in the mapping,
    // so we should just take the last one.
    $delta = max(0, count($available_links) - $inv_delta);
    $link = $available_links[$delta];
    if (is_int($link) && $link > $article_start) {
      return $link + $offset;
    }
    return $link;
  }

}
