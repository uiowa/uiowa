<?php

namespace Drupal\grad_migrate\Plugin\migrate\source;

use Drupal\Component\Utility\Html;
use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;
use Drupal\migrate\Row;
use Drupal\taxonomy\Entity\Term;

/**
 * Basic implementation of the source plugin.
 *
 * @MigrateSource(
 *  id = "d7_admissions_aos",
 *  source_module = "admissions_migrate"
 * )
 */
class AreaOfStudy extends BaseNodeSource {

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
   * Node-to-node mapping for author content.
   *
   * @var array
   */
  protected $authorMapping;

  /**
   * Term-to-term mapping for tags.
   *
   * @var array
   */
  protected $termMapping;

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();
    $query->join('field_data_body', 'intro', 'n.nid = intro.entity_id');
    $query->leftJoin('field_data_field_sub_title', 'subtitle', 'n.nid = subtitle.entity_id');
    $query->leftJoin('field_data_field_undergradtype', 'undergrad_type', 'n.nid = undergradtype.entity_id');
    $query->leftJoin('field_data_field_mail_item_code', 'mail_item_code', 'n.nid = mail_item_code.entity_id');
    $query->leftJoin('field_data_field_alt_names', 'alt_names', 'n.nid = alt_names.entity_id');
    $query->leftJoin('field_data_field_alt_description', 'alt_description', 'n.nid = alt_description.entity_id');
//    $query->leftJoin('field_data_field_academic_group', 'academic_group', 'n.nid = academic_group.entity_id');
//    $query->leftJoin('field_data_field_program_types', 'program_types', 'n.nid = program_types.entity_id');
    $query->leftJoin('field_data_field_degree', 'degree', 'n.nid = degree.entity_id');
    $query->leftJoin('field_data_field_minor', 'minor', 'n.nid = minor.entity_id');
    $query->leftJoin('field_data_field_certificates', 'certificates', 'n.nid = certificates.entity_id');
    $query->leftJoin('field_data_field_preprofessional', 'preprofessional', 'n.nid = preprofessional.entity_id');
    $query->leftJoin('field_data_field_online', 'online', 'n.nid = online.entity_id');
    $query->leftJoin('field_data_field_tracks', 'subprogram', 'n.nid = subprogram_type.entity_id');
    $query->leftJoin('field_data_field_track_type_name', 'subprogram_type', 'n.nid = subprogram_type.entity_id');
    $query->leftJoin('field_data_field_teacher_license', 'teacher_license', 'n.nid = teacher_license.entity_id');
    $query->leftJoin('field_data_field_teaching_desc', 'teacher_desc', 'n.nid = teacher_desc.entity_id');
    $query->leftJoin('field_data_field_honors_courses', 'honors_courses', 'n.nid = honors_courses.entity_id');
    $query->leftJoin('field_data_field_four_year_grad', 'four_year_plan', 'n.nid = four_year_plan.entity_id');
    $query->leftJoin('field_data_field_fouryear_desc', 'four_year_desc', 'n.nid = four_year_desc.entity_id');
    $query->leftJoin('field_data_field_selective', 'selective_admission', 'n.nid = selective_admission.entity_id');
    $query->leftJoin('field_data_field_selective_description', 'selective_admission_desc', 'n.nid = selective_admission_desc.entity_id');
    $query->leftJoin('field_data_field_competitive', 'competitive_admission', 'n.nid = competitive_admission.entity_id');
    $query->leftJoin('field_data_field_competitive_description', 'competitive_admission_desc', 'n.nid = competitive_admission_desc.entity_id');
    $query->leftJoin('field_data_field_dept_url', 'dept_url', 'n.nid = dept_url.entity_id');
    $query->leftJoin('field_data_field_college', 'college', 'n.nid = college.entity_id');
    $query->leftJoin('field_data_field_catalog_url', 'catalog_url', 'n.nid = catalog_url.entity_id');
    $query->leftJoin('field_data_field_progvideo', 'video', 'n.nid = video.entity_id');
    $query->leftJoin('field_data_field_student_profile', 'student_profile', 'n.nid = student_profile.entity_id');
    $query->leftJoin('field_data_field_why', 'why_uiowa', 'n.nid = why_uiowa.entity_id');
    $query->leftJoin('field_data_field_coursework', 'coursework', 'n.nid = coursework.entity_id');
    $query->leftJoin('field_data_field_requirements_intro', 'requirements_intro', 'n.nid = requirements_intro.entity_id');
    $query->leftJoin('field_data_field_requirements', 'requirements_fy', 'n.nid = requirements_fy.entity_id');
    $query->leftJoin('field_data_field_requirements_trans', 'requirements_trans', 'n.nid = requirements_trans.entity_id');
    $query->leftJoin('field_data_field_detaile_transfer_tips', 'detailed_trans_tips', 'n.nid = detailed_trans_tips.entity_id');
    $query->leftJoin('field_data_field_transfer_tips_subtitle', 'transfer_tips_subtitle', 'n.nid = transfer_tips_subtitle.entity_id');
    $query->leftJoin('field_data_field_transfer_tips', 'transfer_tips', 'n.nid = transfer_tips.entity_id');
    $query->leftJoin('field_data_field_requirements_intl', 'requirements_intl', 'n.nid = requirements_intl.entity_id');
    $query->leftJoin('field_data_field_studopps', 'student_opps', 'n.nid = student_opps.entity_id');
    $query->leftJoin('field_data_field_facresources', 'resources', 'n.nid = resources.entity_id');
    $query->leftJoin('field_data_field_careers', 'careers', 'n.nid = careers.entity_id');
    $query->leftJoin('field_data_field_scholarships', 'scholarships', 'n.nid = scholarships.entity_id');
    $query->leftJoin('field_data_field_app_process_intro', 'app_process_intro', 'n.nid = app_process_intro.entity_id');
    $query->leftJoin('field_data_field_fy_app_process', 'app_process_intro_fy', 'n.nid = app_process_intro_fy.entity_id');
    $query->leftJoin('field_data_field_trans_app_process', 'app_process_intro_trans', 'n.nid = app_process_intro_trans.entity_id');
    $query->leftJoin('field_data_field_intl_app_process', 'app_process_intro_intl', 'n.nid = app_process_intro_intl.entity_id');
    $query->leftJoin('field_data_field_visit_type', 'visit_type', 'n.nid = visit_type.entity_id');
    $query->leftJoin('field_data_field_visit_session_description', 'visit_desc', 'n.nid = visit_desc.entity_id');
    $query->leftJoin('field_data_field_progimage', 'image', 'n.nid = image.entity_id');
    $query->leftJoin('field_data_field_searchtag', 'search_tags', 'n.nid = search_tags.entity_id');
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
      ->fields('subtitle', [
        'field_sub_title_value',
      ])
      ->fields('undergrad_type', [
        'field_undergradtype_tid',
      ])
      ->fields('mail_item_code', [
        'field_mail_item_code_value',
      ])
      ->fields('alt_names', [
        'field_alt_names_value',
      ])
      ->fields('alt_description', [
        'field_alt_description_value',
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
    // Strip tags so they don't show up in the field teaser.
    $row->setSourceProperty('body_summary', strip_tags($row->getSourceProperty('body_summary')));

    // Grab the various multi-value fields.
    $tables = [
      'field_data_field_academic_group' => ['field_data_field_academic_group_tid'],
      'field_data_field_program_types' => ['field_data_field_program_types_value'],
    ];
    // Call the parent prepareRow.
    return parent::prepareRow($row);
  }

}
