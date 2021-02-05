<?php

namespace Drupal\writinguniversity_migrate\Plugin\migrate\source;

use Drupal\Component\Utility\Html;
use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;
use Drupal\migrate\Row;
use Drupal\sitenow_migrate\Plugin\migrate\source\ProcessMediaTrait;
use Drupal\taxonomy\Entity\Term;

/**
 * Basic implementation of the source plugin.
 *
 * @MigrateSource(
 *  id = "d7_writinguniversity_blog",
 *  source_module = "writinguniversity_migrate"
 * )
 */
class Blog extends BaseNodeSource {

  use ProcessMediaTrait;

  /**
   * The public file directory path.
   *
   * @var string
   */
  protected $publicPath;

  /**
   * The private file directory path, if any.
   *
   * @var string
   */
  protected $privatePath;

  /**
   * The temporary file directory path.
   *
   * @var string
   */
  protected $temporaryPath;

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();
    $query->join('field_data_body', 'b', 'n.nid = b.entity_id');
    $query->leftJoin('field_data_field_large_featured_blog_image', 'image', 'n.nid = image.entity_id');
    $query->leftJoin('url_alias', 'alias', "alias.source = CONCAT('node/', n.nid)");
    $query = $query->fields('b', [
      'entity_type',
      'bundle',
      'deleted',
      'entity_id',
      'revision_id',
      'language',
      'delta',
      'body_value',
      'body_summary',
      'body_format',
    ])
      ->fields('image', [
        'field_large_featured_blog_image_fid',
        'field_large_featured_blog_image_alt',
        'field_large_featured_blog_image_title',
        'field_large_featured_blog_image_width',
        'field_large_featured_blog_image_height',
      ])
      ->fields('n', [
        'title',
        'created',
        'changed',
        'status',
        'promote',
        'sticky',
      ])
      ->fields('alias', [
        'alias',
      ]);
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [
      'entity_type' => $this->t('(article body) Entity type body content is associated with'),
      'bundle' => $this->t('(article body) Bundle the node associated to the body content belongs to'),
      'deleted' => $this->t('(article body) Indicator for content marked for deletion'),
      'entity_id' => $this->t('(article body) ID of the entity the body content is associated with'),
      'revision_id' => $this->t('(article body) Revision ID for the piece of content'),
      'language' => $this->t('(article body) Language designation'),
      'delta' => $this->t('(article body) 0 for standard sites'),
      'body_value' => $this->t('(article body) Body content'),
      'body_summary' => $this->t('(article body) Body summary content'),
      'body_format' => $this->t('(article body) Body content text format'),
      'title' => $this->t('(node) Node title'),
      'created' => $this->t('(node) Timestamp for node creation date'),
      'changed' => $this->t('(node) Timestamp for node last changed date'),
      'status' => $this->t('(node) 0/1 for Unpublished/Published'),
      'promote' => $this->t('(node) 0/1 for Unpromoted/Promoted'),
      'sticky' => $this->t('(node) 0/1 for Unsticky/Sticky'),
    ];
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'entity_id' => [
        'type' => 'integer',
        'alias' => 'n',
      ],
    ];
  }

  /**
   * Prepare row used for altering source data prior to its insertion.
   *
   * @throws \Drupal\migrate\MigrateException
   */
  public function prepareRow(Row $row) {
    // Process image field if it exists.
    $this->processImageField($row, 'field_large_featured_blog_image');

    // Search for D7 inline embeds and replace with D8 inline entities.
    $content = $row->getSourceProperty('body_value');

    // Replace any inline images, if they exist.
    $content = $this->replaceInlineImages($content);

    $row->setSourceProperty('body_value', $content);

    // Strip tags so they don't show up in the field teaser.
    $row->setSourceProperty('body_summary', strip_tags($row->getSourceProperty('body_summary')));

    // Call the parent prepareRow.
    return parent::prepareRow($row);
  }

  /**
   * Replace inline image tags with media references.
   *
   * Used this as reference: https://stackoverflow.com/a/3195048.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\migrate\MigrateException
   */
  protected function replaceInlineImages($content) {
    $drupal_file_directory = $this->getDrupalFileDirectory();

    // Create a HTML content fragment.
    $document = Html::load($content);

    // Get all the image from the $content.
    $images = $document->getElementsByTagName('img');

    // As we replace the inline images, they are actually
    // removed in the DOMNodeList $images, so we have to
    // use a regressive loop to count through them.
    // See https://www.php.net/manual/en/domnode.replacechild.php#50500.
    $i = $images->length - 1;

    while ($i >= 0) {
      // The current inline image element.
      $img = $images->item($i);
      $src = $img->getAttribute('src');
      // No point in continuing after this point because the
      // image is broken if we don't have a 'src'.
      if ($src) {
        // Process the 'src' into a consistent format.
        $file_path = basename(rawurldecode($src));

        // Attempt to get existing image.
        $fid = $this->getD8FileByFilename($file_path);

        if (!$fid) {
          // Get the prefix to the path for downloading purposes.
          $prefix_path = str_replace('/sites/gc/files/', '', substr($src, 0, strpos($src, $file_path)));

          // Download the file and create the file record.
          $fid = $this->downloadFile($file_path, $this->getSourceBasePath() . $prefix_path, $drupal_file_directory);

          // Get meta data an create the media entity.
          $meta = [];
          foreach (['alt', 'title'] as $name) {
            if ($prop = $img->getAttribute($name)) {
              $meta[$name] = $prop;
            }
          }
          $this->createMediaEntity($fid, $meta);
        }

        // Get the media UUID.
        $uuid = $this->getMid($file_path)['uuid'];

        // There is an issue at this point if we don't have an MID,
        // and we definitely don't want to replace the existing item
        // with a broken media embed.
        if ($uuid) {
          // Create the <drupal-media> element.
          $media_embed = $document->createElement('drupal-media');
          $media_embed->setAttribute('data-entity-uuid', $uuid);
          // @todo Determine how to correctly set the crop.
          //   $media_embed->setAttribute('data-view-mode', 'full_no_crop');
          $media_embed->setAttribute('data-entity-type', 'media');

          // Set the alignment if we can determine it.
          $align = $this->getImageAlign($img);
          if ($align) {
            $media_embed->setAttribute('data-align', $align);
          }

          // Replace the <img> element with the <drupal-media> element.
          $img->parentNode->replaceChild($media_embed, $img);
        }
        // If we weren't able to find or download an image,
        // let's insert a token for cleanup later.
        else {
          $token = $document->createComment('Missing image: ' . $file_path);
          // Replace the <img> element with our token comment.
          $img->parentNode->replaceChild($token, $img);
        }
      }

      $i--;
    }

    // Convert back into a string and return it.
    return Html::serialize($document);
  }

  /**
   * Attempt to determine the image alignment.
   */
  protected function getImageAlign($img) {
    $align = NULL;
    if ($img->getAttribute('align')) {
      $align = $img->getAttribute('align');
    }
    elseif ($img->getAttribute('style')) {
      preg_match('/(?:float: )(left|right)/i', $img->getAttribute('style'), $align_match);
      if ($align_match && !empty($align_match)) {
        $align = $align_match[1];
      }
    }

    return $align;
  }

  /**
   * Get the D7 file record using the filename.
   */
  protected function getD8FileByFilename($filename) {
    $connection = \Drupal::database();
    $query = $connection->select('file_managed', 'f');
    return $query->fields('f', ['fid'])
      ->condition('f.filename', $filename)
      ->execute()
      ->fetchField();
  }

}
