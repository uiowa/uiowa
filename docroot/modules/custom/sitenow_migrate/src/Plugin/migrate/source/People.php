<?php

namespace Drupal\sitenow_migrate\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Row;

/**
 * Basic implementation of the source plugin.
 *
 * @MigrateSource(
 *  id = "people",
 *  source_module = "sitenow_migrate"
 * )
 */
class People extends SqlBase {

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
    $query = $this->select('node', 'n');
    $query->leftJoin('field_data_field_people_first_name', 'fn', 'n.nid = fn.entity_id');
    $query->leftJoin('field_data_field_people_last_name', 'ln', 'n.nid = ln.entity_id');
    $query->leftJoin('field_data_field_person_bio', 'b', 'n.nid = b.entity_id');
    // We are not pulling in the CV at this time--new person node does not have this.
    // $query->join('field_data_field_person_cv', 'cv', 'n.nid = cv.entity_id');
    // Department is dependent on the department taxonomy, which is not migrated currently.
    // $query->join('field_data_field_person_department', 'd', 'n.nid = d.entity_id');.
    $query->leftJoin('field_data_field_person_email', 'em', 'n.nid = em.entity_id');
    $query->leftJoin('field_data_field_person_image', 'im', 'n.nid = im.entity_id');
    $query->leftJoin('field_data_field_person_office', 'o', 'n.nid = o.entity_id');
    $query->leftJoin('field_data_field_person_phone', 'ph', 'n.nid = ph.entity_id');
    $query->leftJoin('field_data_field_person_position', 'pos', 'n.nid = pos.entity_id');
    // Type is dependent on the person type taxonomy, which is not migrated currently.
    // $query->join('field_data_field_person_type', 't', 'n.nid = t.entity_id');.
    $query->leftJoin('field_data_field_person_website', 'w', 'n.nid = w.entity_id');
    $query = $query->fields('n', [
      'nid',
      'title',
      'status',
      'created',
      'changed',
      'promote',
      'sticky',
      'tnid',
    ])
      ->fields('fn', [
        'field_people_first_name_value',
      ])
      ->fields('ln', [
        'field_people_last_name_value',
      ])
      ->fields('b', [
        'field_person_bio_value',
        'field_person_bio_summary',
      ])
      ->fields('em', [
        'field_person_email_email',
      ])
      ->fields('im', [
        'field_person_image_fid',
      ])
      ->fields('o', [
        'field_person_office_value',
      ])
      ->fields('ph', [
        'field_person_phone_value',
      ])
      ->fields('pos', [
        'field_person_position_value',
      ])
      ->fields('w', [
        'field_person_website_url',
      ])
      ->condition('n.type', 'people');
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [
      'nid' => $this->t('Node ID'),
      'title' => $this->t('Node Title'),
      'status' => $this->t('Published/unpublished'),
      'created' => $this->t('Timestamp of creation'),
      'changed' => $this->t('Timestamp of last change'),
      'promote' => $this->t('Promoted'),
      'sticky' => $this->t('Stickied'),
      'tnid' => $this->t('Translation ID'),
      'field_people_first_name_value' => $this->t("Person's first name"),
      'field_people_last_name_value' => $this->t("Person's last name"),
      'field_person_bio_value' => $this->t('Person biography text'),
      'field_person_bio_summary' => $this->t('Person biography short text summary'),
      'field_person_email_email' => $this->t('Person email value (open text field)'),
      'field_person_image_fid' => $this->t('D7 FID for person profile image'),
      'field_person_office_value' => $this->t('Person office location (open text field)'),
      'field_person_phone_value' => $this->t('Person phone number (open text field)'),
      'field_person_position_value' => $this->t('Person position (open text field)'),
      'field_person_website_url' => $this->t('Person website URL (open text field'),
    ];
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'nid' => [
        'type' => 'integer',
        'alias' => 'n',
      ],
    ];
  }

  /**
   * Prepare Row used for altering source data prior to its insertion into the destination.
   */
  public function prepareRow(Row $row) {
    // Determine if the content should be published or not.
    switch ($row->getSourceProperty('status')) {

      case 1:
        $row->setSourceProperty('moderation_state', 'published');
        break;

      default:
        $row->setSourceProperty('moderation_state', 'draft');
    }

    // Get mid from fid for profile image.
    $fid = $row->getSourceProperty('field_person_image_fid');
    if ($fid) {
      $mid = $this->profileImage($fid)['entity_id'];
    }
    if ($mid) {
      $row->setSourceProperty('person_mid', $mid);
    }

    // Check summary, and create one if none exists.
    if (!$row->getSourceProperty('field_person_bio_summary')) {
      $content = $row->getSourceProperty('field_person_bio_value');
      $new_summary = substr($content, 0, 200);
      // Shorten the string until we reach a natural(ish) breaking point.
      $looper = TRUE;
      while ($looper && strlen($new_summary) > 0) {
        switch (substr($new_summary, -1)) {

          case '.':
          case '!':
          case '?':
            $looper = FALSE;
            break;

          case ';':
          case ':':
          case '"':
            $looper = FALSE;
            $new_summary = $new_summary . '...';
            break;

          default:
            $new_summary = substr($new_summary, 0, -1);
        }
      }
      // Strip out any HTML, and set the new summary.
      $new_summary = preg_replace("|<.*?>|", '', $new_summary);
      $row->setSourceProperty('field_person_bio_summary', $new_summary);
    }
    // Call the parent prepareRow.
    return parent::prepareRow($row);
  }

  /**
   * Fetch the media uuid based on the provided filename.
   */
  public function profileImage($fid) {
    $file_data = $this->fidQuery($fid);
    $filename = $file_data['filename'];
    $connection = \Drupal::database();
    $query = $connection->select('file_managed', 'f');
    $query->join('media__field_media_image', 'fmi', 'f.fid = fmi.field_media_image_target_id');
    $result = $query->fields('fmi', ['entity_id'])
      ->condition('f.filename', $filename)
      ->execute();
    return $result->fetchAssoc();
  }

  /**
   * Simple query to get info on the Drupal 7 file based on fid.
   */
  public function fidQuery($fid) {
    $query = $this->select('file_managed', 'f')
      ->fields('f', ['filename'])
      ->condition('f.fid', $fid);
    $results = $query->execute();
    return $results->fetchAssoc();
  }

}
