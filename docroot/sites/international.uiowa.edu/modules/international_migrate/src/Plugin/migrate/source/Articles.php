<?php

namespace Drupal\international_migrate\Plugin\migrate\source;

use Drupal\Component\Utility\Html;
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
      while ($i >= 0) {
        $link = $links->item($i);
        $href = $link->getAttribute('href');
        if (strpos($href, '/node/') === 0 || stristr($href, 'international.uiowa.edu/node/')) {
          $nid = explode('node/', $href)[1];
          if ($lookup = $this->manualLookup($nid)) {
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
            $this->logger->notice('Unable to replace internal link @link in article @article.', [
              '@link' => $href,
              '@article' => $row->getSourceProperty('title'),
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
          // Add the mapped TID to match our tag name.
          $tags[] = $this->tagMapping[$tag_name];
        }
      }
      $row->setSourceProperty('tags', $tags);
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function postImport(MigrateImportEvent $event) {
    // If we haven't finished our migration,
    // don't proceed with the following.
    $migration = $event->getMigration();
    if (!$migration->allRowsProcessed()) {
      return;
    }
    $to_update = [
      1376,
      1391,
      1396,
      1411,
    ];
    if ($this->replaceSpecifiedLinks($to_update)) {
      $this->logger->notice('Preexisting node links updated: @nids', [
        '@nids' => implode(', ', $to_update),
      ]);
    }
    else {
      $this->logger->notice('Unable to update node links: @nids', [
        '@nids' => implode(', ', $to_update),
      ]);
    }
    // Report possible broken links after our known high water mark
    // of articles in which we fixed links.
    $this->reportPossibleLinkBreaks(['node__body' => ['body_value']], 11901);
  }

  /**
   * Replace links for several specified nodes.
   */
  private function replaceSpecifiedLinks($nids) {
    $db = \Drupal::database();
    if (!$db->schema()->tableExists('migrate_map_' . $this->migration->id())) {
      return FALSE;
    }
    $map = $db->select('migrate_map_' . $this->migration->id(), 'm')
      ->fields('m', ['sourceid1', 'destid1'])
      ->execute()
      ->fetchAllKeyed();

    $entity_manager = \Drupal::service('entity_type.manager')
      ->getStorage('node');
    $nodes = $entity_manager->loadMultiple($nids);
    foreach ($nodes as $node) {
      $body_text = $node->body->value;
      $doc = Html::load($body_text);
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
      $body_text = Html::serialize($doc);
      $node->body->value = $body_text;
      $node->save();
    }
  }

  /**
   * Return the destination given a NID on the old site.
   *
   * @param int $nid
   *   The node ID.
   *
   * @return false|string|int
   *   The new node id, path, or FALSE if not in the map.
   */
  protected function manualLookup($nid) {
    // Depending on when the migration is run in production,
    // we may need to offset some of the node ids to account
    // for their new order in node id.
    $offset = 0;
    $article_start = 0;
    $map = [
      // 430 => -1,
      // 430 => 2526,
      // 562 => 981,
      // 562 => 996,
      563 => -1,
      566 => 1016,
      582 => -1,
      584 => 1306,
      // 605 => 'https://globalhealthstudies.uiowa.edu/',
      // 605 => 171,
      // 609 => -1,
      // 609 => 956,
      610 => 1386,
      625 => 81,
      631 => 576,
      // 695 => 1071,
      // 695 => 1096,
      697 => 206,
      699 => 996,
      702 => -1,
      704 => 1031,
      705 => 21,
      707 => 2591,
      711 => 1106,
      777 => 161,
      778 => -1,
      790 => 1381,
      793 => 621,
      809 => 856,
      // 812 => 16,
      // 812 => -1,
      826 => 806,
      827 => 2031,
      // 828 => 826,
      // 828 => 2036,
      829 => 826,
      // 836 => 1061,
      // 836 => 781,
      // 849 => 2001,
      // 849 => 2591,
      851 => 2026,
      853 => 756,
      858 => 1351,
      859 => 756,
      869 => 756,
      931 => 7506,
      1048 => 681,
      1049 => 1376,
      1075 => 966,
      1077 => -1,
      1086 => 1531,
      // 1096 => 636,
      // 1096 => -1,
      1098 => 571,
      1099 => 576,
      1108 => -1,
      1112 => 2031,
      1114 => 786,
      1115 => 791,
      1116 => 971,
      1122 => -1,
      // 1129 => 876,
      // 1129 => 636,
      // 1133 => 2491,
      // 1133 => 786,
      // 1137 => 31,
      // 1137 => 876,
      1138 => 2001,
      1139 => 2001,
      1189 => 1981,
      1221 => -1,
      // 1223 => -1,
      // 1223 => 2036,
      1228 => -1,
      1229 => 1841,
      1232 => -1,
      1233 => -1,
      1992 => -1,
      3254 => 1481,
      3280 => 896,
      3747 => -1,
      3748 => -1,
      3805 => -1,
      3884 => 2001,
      3962 => 1671,
      3965 => 66,
      4045 => -1,
      4068 => -1,
      4896 => -1,
      4104 => 21,
      4117 => 781,
      4174 => -1,
      4179 => -1,
      4188 => 1061,
      4208 => -1,
      4278 => -1,
      4290 => -1,
      4302 => 2491,
      4308 => -1,
      4342 => -1,
      4349 => 'https://www.press-citizen.com/story/opinion/contributors/guest-editorials/2015/03/19/new-media-social-change-middle-east/25022323/',
      4364 => 3206,
      4383 => -1,
      4389 => 2491,
      4418 => -1,
      4419 => 'https://now.uiowa.edu/2015/04/learning-about-arab-spring-global-context',
      4451 => 1061,
      4457 => 'https://www.press-citizen.com/story/opinion/contributors/writers-group/2015/04/25/obama-tell-gulf-leaders/26321961/',
      4474 => 2026,
      4482 => -1,
      4492 => 1376,
      4496 => -1,
      4502 => -1,
      4511 => -1,
      4513 => -1,
      4546 => 'https://exchange.prx.org/search/pieces?q=worldcanvass%3A+don+quixote&commit=Search',
      4693 => -1,
      // 4700 => 1061,
      // 4700 => 2496,
      4703 => -1,
      4731 => 306,
      4740 => -1,
      // 4777 => 1061,
      // 4777 => -1,
      4778 => 1061,
      4838 => -1,
      4862 => -1,
      4872 => -1,
      4873 => -1,
      4875 => -1,
      4882 => 1061,
      4888 => -1,
      4895 => -1,
      4898 => -1,
      4900 => -1,
      4901 => -1,
      4902 => -1,
      4931 => -1,
      5110 => 1061,
      5122 => 1061,
      5146 => -1,
      5176 => -1,
      5180 => 1061,
      5145 => -1,
      5203 => -1,
      5228 => 451,
      5230 => 751,
      5241 => 1061,
      5246 => 1061,
      5267 => -1,
      5285 => 1061,
      // 5286 => 781,
      // 5286 => 1061,
      5338 => 1061,
      5365 => -1,
      5372 => 2,
      5374 => -1,
      // 5430 => 751,
      // 5430 => 5056,
      5526 => 1061,
      5566 => -1,
      5756 => 'https://studyabroad.sit.edu/',
      5791 => -1,
      5836 => -1,
      6341 => -1,
      // 6361 => 966,
      // 6361 => 1061,
      6521 => 'https://fundforeducationabroad.org/',
      6986 => 'https://www.press-citizen.com/',
      7096 => 1061,
      7151 => -1,
      7161 => 1061,
      // 7166 => -1,
      // 7166 => 'https://www.press-citizen.com/',
      7386 => 'https://www.press-citizen.com/',
      7621 => 21,
      7701 => 786,
      7741 => 1521,
      7756 => 1521,
      8006 => -1,
      8011 => 'https://www.press-citizen.com/',
      8026 => 786,
      8111 => 'https://www.press-citizen.com/',
      8186 => -1,
      8191 => 'https://dailyiowan.com/',
      8301 => 1836,
      8406 => 'https://www.press-citizen.com/',
      8751 => -1,
      8872 => 896,
      8966 => -1,
      9021 => 1061,
      9111 => 'https://www.press-citizen.com/',
      9216 => 1061,
      9271 => 'https://www.press-citizen.com/',
      9286 => 'https://dailyiowan.com/',
      9471 => 7146,
      9586 => 1061,
      9656 => 1061,
      9856 => 1061,
      9886 => 7471,
      10031 => 'https://www.press-citizen.com/',
      10066 => 'https://www.press-citizen.com/',
      10146 => 'https://dailyiowan.com/',
      10256 => 7471,
      10266 => 7746,
      10311 => -1,
      10451 => 2501,
      10806 => 1061,
      10856 => 2506,
      10896 => 'https://www.press-citizen.com/',
      11026 => 'https://www.press-citizen.com/',
      11031 => 1061,
      11151 => 'https://www.press-citizen.com/',
      11156 => 2786,
      11266 => 1061,
      11286 => 1521,
      11311 => 2596,
      11356 => 8486,
      11361 => 2776,
      11366 => 2776,
      11371 => 1671,
      11406 => 'https://www.press-citizen.com/',
      11431 => 1986,
      11606 => 781,
      11616 => 2001,
      11621 => 781,
      11656 => 8651,
      11681 => 8671,
      11686 => 8676,
      11691 => 8681,
      11701 => 8691,
      12191 => 9116,
      12201 => 9126,
      12276 => 9181,
      12341 => 9216,
      12346 => 9221,
      12356 => 9226,
      12371 => 1061,
      12471 => 1061,
      12501 => 'https://korn.uiowa.edu',
      12581 => 1061,
      12601 => 2536,
      12796 => 9581,
      12921 => 1061,
      13136 => 1606,
      13156 => 'https://www.press-citizen.com/',
      13196 => 1061,
      13266 => 'https://dailyiowan.com/2020/02/23/university-of-iowa-panel-discusses-response-concerns-on-coronavirus/',
      13396 => -1,
      13436 => 10026,
      13451 => 10036,
      13726 => 10266,
      13776 => 10301,
      13861 => 751,
      13876 => -1,
      // 13891 => 616,
      // 13891 => 636,
      13896 => 616,
      // 13901 => 161,
      // 13901 => 616,
      13921 => 191,
      14046 => 10571,
      14061 => 961,
      // 14096 => 1061,
      // 14096 => 781,
      // 14111 => 2536,
      // 14111 => 10551,
      14116 => 10556,
      14121 => 10561,
      14136 => 2791,
      14156 => 1521,
      14176 => 2801,
      14256 => -1,
      14361 => 2821,
      14366 => 10741,
      14376 => 10751,
      14381 => 2806,
      14446 => 616,
      14471 => 2811,
      14881 => 11171,
      14486 => 'https://now.uiowa.edu/2021/02/ui-named-top-producer-fulbright-students-sixth-consecutive-year',
      14606 => -1,
      14616 => 2811,
      14646 => 2816,
      14771 => 11071,
      14781 => 11081,
      14931 => 11221,
      14956 => 776,
      14981 => 1356,
      15011 => 11281,
      15071 => 2006,
      15091 => 11356,
      15291 => 11511,
      15331 => 2531,
      15356 => 1666,
      15366 => 2826,
      15371 => 2831,
      15386 => 11566,
      15456 => 11631,
      15496 => 11666,
      15581 => 11731,
      15596 => 11746,
      15741 => 11861,
    ];
    $mapped = $map[$nid] ?? FALSE;
    if (is_int($mapped) && $mapped > $article_start) {
      return $mapped + $offset;
    }
    return $mapped;
  }

}
