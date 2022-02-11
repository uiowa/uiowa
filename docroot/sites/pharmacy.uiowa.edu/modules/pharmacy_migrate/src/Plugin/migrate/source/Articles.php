<?php

namespace Drupal\pharmacy_migrate\Plugin\migrate\source;

use Drupal\Component\Utility\Html;
use Drupal\migrate\Row;
use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;
use Drupal\sitenow_migrate\Plugin\migrate\source\ProcessMediaTrait;

/**
 * Migrate Source plugin.
 *
 * @MigrateSource(
 *   id = "pharmacy_articles",
 *   source_module = "node"
 * )
 */
class Articles extends BaseNodeSource {
  use ProcessMediaTrait;

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();
    $query->leftJoin('url_alias', 'alias', "alias.source = CONCAT('node/', n.nid)");
    $query->fields('alias', ['alias']);
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
    parent::prepareRow($row);
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
      $created_year = date('Y', $row->getSourceProperty('created'));

      while ($i >= 0) {
        $link = $links->item($i);
        $href = $link->getAttribute('href');

        // Unlink anchors in body from articles before 2016.
        if ($created_year < 2016) {
          $text = $doc->createTextNode($link->nodeValue);
          $link->parentNode->replaceChild($text, $link);
          $doc->saveHTML();
        }
        else {
          if (strpos($href, '/node/') === 0 || stristr($href, 'pharmacy.uiowa.edu/node/')) {
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
      $body[0]['value'] = $html;

      $row->setSourceProperty('body', $body);
    }

    // Process the image field.
    $image = $row->getSourceProperty('field_article_image');

    if (!empty($image)) {
      $mid = $this->processImageField($image[0]['fid'], $image[0]['alt'], $image[0]['title']);
      $row->setSourceProperty('field_article_image_mid', $mid);
    }

    // Create combined array of taxonomy terms to map to tags.
    $tags = [];

    $reference_fields = [
      'field_department_multi',
      'field_audience_multi',
      'field_news_category',
    ];

    foreach ($reference_fields as $field_name) {
      if ($refs = $row->getSourceProperty($field_name)) {
        foreach ($refs as $ref) {
          $tags[] = $ref['tid'];
        }
      }
    }

    $row->setSourceProperty('tags', $tags);

    return TRUE;
  }

  /**
   * Return the destination given a NID on the old site.
   *
   * @param int $nid
   *   The node ID.
   *
   * @return false|string
   *   The new path or FALSE if not in the map.
   */
  protected function manualLookup($nid) {
    $map = [
      1122 => '/people/jeanine-p-abrons',
      1234 => '/people/guohua',
      5022 => '/people/ethan-j-anderson',
      3993 => '/people/karen-ann-baker',
      497 => '/people/elizabeth-beltz',
      606 => '/people/nicole-k-brogden',
      896 => '/people/mike-brownlee',
      15436 => '/people/andrean-burnett',
      17056 => '/people/renan-cabrera-lafuente',
      585 => '/people/matthew-cantrell',
      3484 => '/people/cole-g-chapman',
      498 => '/people/elizabeth-chrischilles',
      650 => '/people/jay-dean-currie',
      16506 => '/people/david-dick',
      520 => '/people/maureen-d-donovan',
      833 => '/people/jonathan-doorn',
      506 => '/people/william-r-doucette',
      748 => '/people/vern-kent-duba',
      515 => '/people/michael-w-duffel',
      4491 => '/people/satheesh-elangovan',
      757 => '/people/erika-j-ernst',
      619 => '/people/michael-e-ernst',
      792 => '/people/brett-faine',
      526 => '/people/t-michael-farley',
      980 => '/people/jennifer-fiegel',
      4128 => '/people/lorin-fisher',
      586 => '/people/michelle-fravel',
      15441 => '/people/marie-e-gaine',
      525 => '/people/amber-m-goedken',
      3946 => '/people/ramprakash-govindarajan',
      642 => '/people/ronald-herman',
      851 => '/people/morgan-sayler-herring',
      626 => '/people/jim-hoehns',
      782 => '/people/ryan-b-jacobsen',
      473 => '/people/zhendong-jin',
      1049 => '/people/jill-kauer',
      4835 => '/people/korey-kennelty',
      597 => '/people/robert-j-kerns',
      601 => '/people/laura-e-knockel',
      8486 => '/people/lee-kral',
      1011 => '/people/donald-e-letendre',
      4654 => '/people/kashelle-lockman',
      740 => '/people/leonard-richard-macgillivray',
      3992 => '/people/cindy-lou-marek',
      4527 => '/people/james-martin',
      783 => '/people/nic-mastascusa',
      587 => '/people/deanna-l-mcdanel',
      517 => '/people/gary-milavetz',
      15276 => '/people/benjamin-miskle',
      15186 => '/people/reza-nejadnik',
      12751 => '/people/theodore-pham-nguyen',
      6051 => '/people/eric-nuxoll',
      945 => '/people/stuart-k-pitman',
      486 => '/people/linnea-polgreen',
      4480 => '/people/laura-l-ponto',
      3756 => '/people/james-b-ray',
      3798 => '/people/mary-elizabeth-ray',
      539 => '/people/jeffrey-c-reist',
      738 => '/people/kevin-g-rice',
      1044 => '/people/dave-l-roman',
      828 => '/people/aliasger-k-salem',
      1128 => '/people/mary-chen-schroeder',
      957 => '/people/jordan-l-schultz',
      845 => '/people/jenny-l-seyfer',
      1136 => '/people/ashley-spies',
      1163 => '/people/lewis-l-stevens',
      683 => '/people/john-swegle',
      919 => '/people/traviss-tubbs',
      510 => '/people/julie-m-urmie',
      553 => '/people/stevie-veach',
      998 => '/people/susan-staggs-vos',
      4479 => '/people/george-j-weiner',
      1009 => '/people/sara-ann-wiedenfeld',
      634 => '/people/matthew-j-witry',
    ];

    return $map[$nid] ?? FALSE;
  }

}
