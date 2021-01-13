<?php

namespace Drupal\grad_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;

/**
 * Basic implementation of the source plugin.
 *
 * @MigrateSource(
 *  id = "d7_grad_thesis_defense",
 *  source_module = "grad_migrate"
 * )
 */
class ThesisDefense extends BaseNodeSource {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();
    $query->leftJoin('field_data_field_thesis_location', 'l', 'n.nid = l.entity_id');
    $query->leftJoin('field_data_field_thesis_defense_date', 'd', 'n.nid = d.entity_id');
    $query->leftJoin('field_data_field_thesis_firstname', 'fn', 'n.nid = fn.entity_id');
    $query->leftJoin('field_data_field_thesis_lastname', 'ln', 'n.nid = ln.entity_id');
    $query->leftJoin('field_data_field_thesis_title', 'tt', 'n.nid = tt.entity_id');
    $query->leftJoin('field_data_field_thesis_department', 'td', 'n.nid = td.entity_id');
    $query->leftJoin('field_data_upload', 'u', 'n.nid = u.entity_id');
    $query->leftJoin('field_data_field_d8_migration_status', 's', 'n.nid = s.entity_id');

    $query = $query->fields('n', [
      'title',
      'created',
      'changed',
      'status',
      'promote',
      'sticky',
    ])
      ->fields('l', [
        'field_thesis_location_value',
        'field_thesis_location_format',
      ])
      ->fields('d', [
        'field_thesis_defense_date_value',
      ])
      ->fields('fn', [
        'field_thesis_firstname_value',
        'field_thesis_firstname_format',
      ])
      ->fields('ln', [
        'field_thesis_lastname_value',
        'field_thesis_lastname_format',
      ])
      ->fields('tt', [
        'field_thesis_title_value',
        'field_thesis_title_format',
      ])
      ->fields('td', [
        'field_thesis_department_value',
      ])
      ->fields('u', [
        'delta',
        'upload_fid',
        'upload_display',
        'upload_description',
      ])
      ->fields('s', [
        'field_d8_migration_status_value',
      ]);
    return $query;
  }

  /**
   * Prepare row used for altering source data prior to its insertion.
   */
  public function prepareRow(Row $row) {
    // Get our multi-value fields.
    $additional_fields = [
      'field_data_field_thesis_chair' => [
        'field_thesis_chair_value',
      ],
    ];
    $this->fetchAdditionalFields($row, $additional_fields);

    // Grab the mapped FID for the file upload field..
    $original_fid = $row->getSourceProperty('upload_fid');
    if (isset($original_fid)) {
      $row->setSourceProperty('upload_fid', $this->getFid($original_fid, 'migrate_map_d7_grad_file'));
    }

    $old_date_format = $row->getSourceProperty('field_thesis_defense_date_value');
    if (isset($old_date_format)) {
      // There's an extra formatter 'T' that can be removed
      // or handled by the createFromFormat.
      // In this case, we should be able to remove it.
      $old_date_format = str_replace('T', ' ', $old_date_format);
      $row->setSourceProperty('field_thesis_defense_date_value', \DateTime::createFromFormat('Y-m-d H:i:s', $old_date_format)->getTimestamp());
    }

    // Get MAUI code from old code used.
    $old_major_code = $row->getSourceProperty('field_thesis_department_value');
    if (isset($old_major_code)) {
      // If we have a match, set the new code.
      if ($new_code = $this->mapProgramLists($old_major_code)) {
        $row->setSourceProperty('field_thesis_department_value', $new_code);
      }
      // Otherwise...
      else {
        var_dump($old_major_code);
      }
    }

    // Call the parent prepareRow.
    return parent::prepareRow($row);
  }

  /**
   * Returns new program code using old code.
   */
  public function mapProgramLists($old_code) {
    $program_list_map = [
      '003' => "SPEECH_PATH_AUD",
      '004' => "CHEM",
      '008' => "ENGL",
      '009f' => "FREN_FRANC",
      '012' => "GEOS",
      '016' => "HIST",
      '01h' => "ART_HIST",
      '025' => "MUSIC",
      '026' => "PHIL",
      '028' => "HLTH_SPORT",
      '02a' => "BIOLOGY",
      '030' => "POL_SCI",
      '031' => "PSYCH",
      '032' => "RELIG_STDS",
      '034' => "SOCIOL",
      '035' => "SPAN",
      '036' => "COMM",
      '03h' => "SPEECH_HEAR_SCI",
      '042' => "SOC_WK",
      '043' => "CLASSICS",
      '044' => "GEOG",
      '045' => "AM_STDS",
      '060' => "ANAT_CELL_BIOL",
      '061' => "MICROBIOL",
      '06ba' => "BUSN",
      '06e' => "ECON",
      '06f' => "BUSN:SP_FINANCE",
      '071' => "PHARMC",
      '072' => "MOL_PHYS_BIOPHYS",
      '077' => "FREE_RADIC_RAD",
      '07b' => "EDUC_PLS",
      '07C' => "CRSD",
      '07h' => "EDUC_PLS:SP_HIGHER_ED",
      '07p' => "PSYCH_QUANT",
      '07r' => "SCI_EDUC",
      '07z' => "TCH_LRN",
      '099' => "BIOCHEM",
      '101' => "PHYS_THER",
      '113' => "ANTH",
      '127' => "GENETICS",
      '132' => "NEUROSCI",
      '142' => "MOL_CELL_BIOL",
      '146' => "PHAR",
      '148' => "IMMUN",
      '151' => "ORAL_SCI",
      '164' => "SEC_LANG_ACQ",
      '171' => "BIO_STAT",
      '173' => "EPID",
      '175' => "OCC_ENV_HLTH",
      '192' => "HLTH_SVC_POL",
      '196' => "NURS",
      '198' => "HUMAN_TOX",
      '19j' => "JRNL",
      '19m' => "MASS_COMMS",
      '200' => "INFORM",
      '22a' => "APPL_MATH_CS",
      '22b' => "STAT",
      '22c' => "COMP_SCI",
      '22m' => "MATH",
      '29p' => "PHYSICS",
      '46h' => "PHAR:SP_PHARM",
      '46m' => "PHAR:SP_MED_NAT_PROD_CHEM",
      '48c' => "COMPARATIVE_LIT",
      '48f' => "FILM_STDS",
      '531' => "BIOMED_ENGR",
      '532' => "CHEM_BIO_ENGR",
      '533' => "CIV_ENV_ENGR",
      '535' => "ELEC_COMP_ENGR",
      '536' => "IND_ENGR",
      '538' => "MECH_ENGR",
      'HHP' => "HLTH_HUM_PHYS",
    ];

    if (isset($program_list_map[$old_code])) {
      return $program_list_map[$old_code];
    }

    return FALSE;
  }

}
