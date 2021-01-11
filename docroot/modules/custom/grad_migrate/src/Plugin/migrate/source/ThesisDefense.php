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
        // @todo Determine logic for when there is no match.
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
      // Accounting => Business Administration - Accounting.
      '06a' => 'BUSN:SP_ACTG',
      // Actuarial Science.
      '22t' => 'ACT_SCI',
      // African American World Studies.
      '134' => 'AFR_AM_WORLD',
      // American Studies.
      '045' => 'AM_STDS',
      // Anatomy and Cell Biology.
      '060' => 'ANAT_CELL_BIOL',
      // Anthropology.
      '113' => 'ANTH',
      // Appl Math and Comp Sci => Applied Mathematical and Computational Sciences
      '22a' => 'APPL_MATH_CS',
      // Art.
      '001' => 'ART',
      // Art History.
      '01h' => 'ART_HIST',
      // Asian Civilizations.
      '39c' => 'ASIAN_CIV',
      // Astronomy.
      '29a' => 'ASTR',
      // Biochemistry.
      '099' => 'BIOCHEM',
      // Biomedical Engineering.
      '531' => 'BIOMED_ENGR',
      // Biostatistics.
      '171' => 'BIO_STAT',
      // Business Administration.
      '06ba' => 'BUSN',
      // Chemical and Biochemical Engr => Chemical and Biochemical Engineering.
      '532' => 'CHEM_BIO_ENGR',
      // Chemistry.
      '004' => 'CHEM',
      // Civil and Environmental Engr => Civil and Environmental Engineering.
      '533' => 'CIV_ENV_ENGR',
      // Classics.
      '043' => 'CLASSICS',
      // Clinical Investigation.
      '194' => 'CLIN_INV_GRAD',
      // College Teaching.
      '190' => 'COLL_TCH',
      // Comm Studies => Communication Studies.
      '036' => 'COMM',
      // Community and Behavioral Health.
      '172' => 'COMM_BEH_HLTH',
      // Computer Science.
      '22c' => 'COMP_SCI',
      // Creative Writing-Writers Wksp => English - Creative Writing.
      '08c' => 'ENGL:SP_CREAT_WRT',
      // Dance.
      '137' => 'DANCE',
      // Dental Public Health.
      '11d' => 'DENT_PH',
      // Dietetic Internship Program.
      '65d' => 'DIET_INTERN',
      // Economics.
      '06e' => 'ECON',
      // Electrical and Computer Engr => Electrical and Computer Engineering.
      '535' => 'ELEC_COMP_ENGR',
      // Elementary Education.
      '07e' => 'ELEM_EDUC',
      // English.
      '008' => 'ENGL',
      // Epidemiology.
      '173' => 'EPID',
      // Film And Video Production.
      '48v' => 'FILM_VIDEO',
      // Film Studies.
      '48f' => 'FILM_STDS',
      // Finance.
      '06f' => 'FINANCE',
      // Free Radical and Radiation Biol => Free Radical and Radiation Biology.
      '077' => 'FREE_RADIC_RAD',
      // French and Francophone World Studies.
      '009f' => 'FREN_FRANC',
      // Gender, Women's and Sexuality Studies.
      '131' => 'WM_STDS',
      // Genetics.
      '127' => 'GENETICS',
      // Geography.
      '044' => 'GEOG',
      // Geoscience.
      '012' => 'GEOS',
      // German.
      '013' => 'GERMAN',
      // Global Health Studies.
      '152' => 'GLOB_HLTH',
      // Greek.
      '014' => 'GREEK',
      // Health and Human Physiology.
      'HHP' => 'HLTH_HUM_PHYS',
      // Health Informatics => Informatics - Health Informatics
      '161' => 'INFORM:SP_HLTH_INFORM',
      // Health Management And Policy
      '174' => 'HLTH_MGMT_POL',
      // Health Services And Policy
      '192' => 'HLTH_SVC_POL',
      // History.
      '016' => 'HIST',
      // Human Toxicology.
      '198' => 'HUMAN_TOX',
      // Immunology.
      '148' => 'IMMUN',
      // Industrial Engineering.
      '536' => 'IND_ENGR',
      // Informatics.
      '200' => 'INFORM',
      // Journalism.
      '19j' => 'JRNL',
      // Latin.
      '020' => 'LATIN',
      // Libr and Info Sci => Library and Information Science.
      '021' => 'LIBR_INFO_SCI',
      // Linguistics.
      '103' => 'LING',
      // Marketing => Business Administration - Marketing.
      '06m' => 'BUSN:SP_MKTG',
      // Mass Communications.
      '19m' => 'MASS_COMMS',
      // Mathematics.
      '22m' => 'MATH',
      // Mechanical Engineering.
      '538' => 'MECH_ENGR',
      // Med Sci Trng Prog-Md/Phd => Medical Scientist Training Program.
      '128' => 'MSTP',
      // Microbiology.
      '061' => 'MICROBIOL',
      // Molecular And Cellular Biology.
      '142' => 'MOL_CELL_BIOL',
      // Molecular Biology.
      '242' => 'MOL_BIOL',
      // Molecular Physiology and Biophys => Molecular Physiology and Biophysics
      '072' => 'MOL_PHYS_BIOPHYS',
      // Mph Program => MPH Program
      '170' => 'MASTERS_PH',
      // Music.
      '025' => 'MUSIC',
      // Neuroscience.
      '132' => 'NEUROSCI',
      // Nonfiction Writing => English - Nonfiction Writing.
      '08n' => 'ENGL:SP_NONFIC',
      // Nursing.
      '196' => 'NURS',
      // Nursing Informatics => Nursing - Nursing Informatics
      '96h' => 'NURS:SP_NURS_INFOR',
      // Occupational and Environ Health => Occupational and Environmental Health
      '175' => 'OCC_ENV_HLTH',
      // Oral And Maxillofacial Surgery.
      '087' => 'ORAL_MAX_SURG',
      // Oral Science.
      '151' => 'ORAL_SCI',
      // Orthodontics.
      '089' => 'ORTHODONTICS',
      // Pathology.
      '069' => 'PATHOLOGY',
      // Pharmaceutics => Pharmacy - Pharmaceutics.
      '46h' => 'PHAR:SP_PHARM',
      // Pharmacology.
      '071' => 'PHARMC',
      // Pharmacy.
      '146' => 'PHAR',
      // Philosophy.
      '026' => 'PHIL',
      // Physical Rehabilitation Science.
      '182' => 'PHYS_REH_SCI',
      // Physics.
      '29p' => 'PHYSICS',
      // 030|Political Science
      // 063|Prev Med and Env Hlth
      // 96z|Prof Nursing and Healthcare Prac
      // 084|Prosthodontics
      // 07p|Psych and Quant Fndtns
      // 031|Psychology
      // Gph|Public Health Cert-Grad
      // 07C|Rehabilitation and Counselor Education
      // 32z|Religion
      // 032|Religious Studies
      // 160|Rhetorics Of Inquiry
      // 041|Russian
      // 25s|Sacred Music
      // 07r|Science Education
      // Tep|Sec Tchr Cert Prog
      // 164|Second Language Acquisition
      // 07s|Secondary Education
      // 07f|Social Foundations Of Educ
      // 042|Social Work
      // 034|Sociology
      // 035|Spanish
      // 07u|Special Education
      // 03h|Speech And Hearing Science
      // 003|Speech Path and Audio
      // 185|Statistical Genetics
      // 22b|Statistics
      // 86p|Stomatology
      // 07z|Teaching And Learning
      // 049|Theatre Arts
      // 150|Third World Dev Support
      // 163|Translational Biomedicine
      // 102|Urban and Regl Plan
      // 231|Women's Studies
      // Wks|Workshop Student
      // Other.
      // @todo Add 'Other' option to allowed values function.
      'Other' => 'OTHER',

      // I can't find a match for these.
      // 96j|Advanced Practice Nursing.
      // 02a|Biology
      // 156|Biosciences Program
      // 108|Center For The Book
      // Roi|Cert In Rhet Of Inq
      // 46c|Clin and Admin Pharmacy
      // 07d|Educational Admin
      // 083|Endodontics
      // 06t|Entrepreneurship
      // 6nx|Executive Mba
      // 27b|Exercise Science
      // 009|French
      // 028|Health And Sport Studies
      // 191|Health Communication
      // 28b|Hlth, Leis and Sport Studies
      // 07w|Instr Des and Tech
      // 027|Integrative Physiology
      // 155|Interdiscipl Studies-Master S
      // 125|Interdisciplinary Studies-Phd
      // 6nh|Intl Exec Mba
      // 169|Leisure Studies
      // 06n|Mba Program
      // 6np|Mba-Pgm Emerg Mgrs
      // 6nv|Mba-Pm
      // 197|Medical Education Program
      // Fsa|Nondegree Stdy Abrd
      // 96y|Nursing Service Administration
      // 082|Operative Dentistry
      // 090|Pediatric Dentistry
      // 92p|Periodontology
      // 147|Physician Assistant Studies
      // 72a|Physiology and Biophysics

      // I might have found a match, but not sure.
      // 153|Aging Studies Program =? 'AGING' => "Aging and Longevity Studies".
      // 149|Amer Indian and Native Studies =? 'AM_IND_NAT' => "Native American and Indigenous Studies".
      // 7CE|Counseling, Rehab and Stdnt Dev =? 'CRSD:SP_ST_DEV_PSE' => "Rehabilitation and Counselor Education - Student Development in Postsecondary Education"
      // 07h|Higher Education =? 'EDUC_PLS:SP_HIGHER_EDUC' => "Educational Policy and Leadership Studies - Higher Education"
      // 06j|Management and Orgs =? 'BUSN:SP_MGMT_ORGS' => "Business Administration - Management"
      // 06k|Management Sciences =? 'BUSN:SP_MGMT_SCI' => "Business Administration - Business Analytics"
      // 46m|Med and Nat Prod Chemistry =? 'PHAR:SP_MED_NAT_PROD_CHEM' => "Pharmacy - Medicinal & Natural Products Chemistry"
      // 101|Phys Therapy and Rehab Science =? 'PHYS_THER' => "Physical Therapy"

      // These seem to have a match, but its not in the list.
      // 48l|Comparative Lit - Translation =? 'COMP_LIT_TRANSL' => "Comparative Literature - Translation"
      // 48c|Comparative Literature =? 'COMPARATIVE_LIT' => "Comparative Literature"
      // 07b|Ed Policy and Leadership Studies =? 'EDUC_PLS' => "Educational Policy and Leadership Studies"
    ];

    if (isset($program_list_map[$old_code])) {
      return $program_list_map[$old_code];
    }

    return FALSE;
  }

}
