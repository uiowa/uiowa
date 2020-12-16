<?php

namespace Drupal\sitenow_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;

/**
 * Basic implementation of the source plugin.
 *
 * @MigrateSource(
 *  id = "people",
 *  source_module = "sitenow_migrate"
 * )
 */
class People extends BaseNodeSource {

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
   *
   * We are not pulling in the CV at this time--new person node does not have
   * this. Department is dependent on the department taxonomy, which is not
   * migrated currently. Type is dependent on the person type taxonomy, which
   * is not migrated currently.
   */
  public function query() {
    $query = parent::query();
    $query->leftJoin('field_data_field_people_first_name', 'fn', 'n.nid = fn.entity_id');
    $query->leftJoin('field_data_field_people_last_name', 'ln', 'n.nid = ln.entity_id');
    $query->leftJoin('field_data_field_person_bio', 'b', 'n.nid = b.entity_id');
    $query->leftJoin('field_data_field_person_email', 'em', 'n.nid = em.entity_id');
    $query->leftJoin('field_data_field_person_image', 'im', 'n.nid = im.entity_id');
    $query->leftJoin('field_data_field_person_office', 'o', 'n.nid = o.entity_id');
    $query->leftJoin('field_data_field_person_phone', 'ph', 'n.nid = ph.entity_id');
    $query->leftJoin('field_data_field_person_position', 'pos', 'n.nid = pos.entity_id');
    $query->leftJoin('field_data_field_person_website', 'w', 'n.nid = w.entity_id');

    $query = $query->fields('fn', [
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
   * Prepare row used for altering source data prior to insertion.
   */
  public function prepareRow(Row $row) {

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
      $new_summary = $this->extractSummaryFromText($content);
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

}
