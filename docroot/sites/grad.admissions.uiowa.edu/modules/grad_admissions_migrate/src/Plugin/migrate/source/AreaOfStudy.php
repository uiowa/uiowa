<?php

namespace Drupal\grad_admissions_migrate\Plugin\migrate\source;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\State\StateInterface;
use Drupal\migrate\Event\MigrateImportEvent;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\pathauto\AliasCleanerInterface;
use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Basic implementation of the source plugin.
 *
 * @MigrateSource(
 *  id = "d7_grad_admissions_aos",
 *  source_module = "node"
 * )
 */
class AreaOfStudy extends BaseNodeSource implements ContainerFactoryPluginInterface {
  /**
   * The pathauto.alias_cleaner service.
   *
   * @var \Drupal\pathauto\AliasCleanerInterface
   */
  protected $aliasCleaner;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, StateInterface $state, ModuleHandlerInterface $module_handler, FileSystemInterface $file_system, EntityTypeManager $entityTypeManager, AliasCleanerInterface $aliasCleaner) {
    $this->aliasCleaner = $aliasCleaner;
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration, $state, $module_handler, $file_system, $entityTypeManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('state'),
      $container->get('module_handler'),
      $container->get('file_system'),
      $container->get('entity_type.manager'),
      $container->get('pathauto.alias_cleaner')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    parent::prepareRow($row);

    // Combine link fields into one.
    $related_links = [];

    if ($dept_url = $row->getSourceProperty('field_grad_dept')) {
      $related_links[] = [
        'url' => $dept_url[0]['url'],
        'title' => $dept_url[0]['title'],
      ];
    }

    if ($catalog_url = $row->getSourceProperty('field_grad_dept')) {
      $related_links[] = [
        'url' => $catalog_url[0]['url'],
        'title' => $catalog_url[0]['title'],
      ];
    }

    if ($college_url = $row->getSourceProperty('field_grad_college')) {
      $related_links[] = [
        'url' => $college_url[0]['url'],
        'title' => $college_url[0]['title'],
      ];
    }

    $row->setSourceProperty('custom_related_links', $related_links);

    if ($ref = $row->getSourceProperty('field_grad_costs')) {
      if ($lookup = $this->manualLookup($ref['nid'])) {
        $row->setSourceProperty('custom_domestic_cost', $lookup);
        $this->logger->info('Swap node @nid for @estimated_cost', [
          '@nid' => $ref['nid'],
          '@estimated_cost' => $row->getSourceProperty('title'),
        ]);
      }
    }

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
      // source_nid => target_nid,
      // MAc. mac-program-estimated-costs.
      121 => 211,
      // MD. md-program-estimated-costs.
      112 => 246,
      // JD. jd-program-estimated-costs.
      415 => 256,
      // SJD. sjd-program-estimated-costs.
      111 => 266,
      // LLM. llm-program-estimated-costs.
      421 => 276,
      // MSL. master-studies-law-msl-program-estimated-costs.
      583 => 286,
      // PharmD. pharmd-program-estimated-costs.
      116 => 296,
      // DNP. doctor-nursing-practice-dnp-program-estimated-costs.
      113 => 306,
      // DPT. dpt-program-estimated-costs.
      131 => 316,
      // MPH. master-public-health-program-estimated-costs.
      118 => 326,
      // Public health (not MPH). graduate-programs-public-health-estimated-costs.
      103 => 336,
      // Sustainable Water Development. sustainable-water-development-certificate-estimated-costs.
      931 => 346,
      // Oral Science Program. oral-science-program-estimated-costs.
      115 => 356,
      // Applied Math & Computational Sciences. applied-math-computational-sciences-estimated-costs.
      105 => 366,
      // LIS. library-information-science-estimated-costs.
      109 => 376,
      // Second Language Acquisition. second-language-acquisition-estimated-costs.
      119 => 386,
      // Grad Programs in Pharmacy. graduate-programs-pharmacy-estimated-costs.
      117 => 396,
      // Informatics. informatics-estimated-costs.
      412 => 406,
      // Dental Public Health. dental-public-health-estimated-costs.
      106 => 416,
      // Urban & Regional Planning. urban-regional-planning-estimated-costs.
      120 => 426,
      // Endodontics. certificate-program-endodontics-estimated-costs.
      132 => 436,
      // DDS. doctor-dental-surgery-dds-program-estimated-costs.
      107 => 446,
      // Speech Pathology & Audiology. speech-pathology-and-audiology-ma-or-aud-estimated-costs.
      417 => 456,
      // Health Administration. health-administration-program-estimated-costs.
      108 => 466,
      // Business (PhD). phd-programs-business-estimated-costs.
      96 => 476,
      // Education. education-estimated-costs.
      101 => 486,
      // Liberal Arts & Sciences. graduate-programs-liberal-arts-sciences-costs.
      99 => 496,
      // Engineering. engineering-estimated-costs.
      102 => 506,
      // Nursing (PhD). phd-program-nursing-estimated-costs.
      133 => 516,
      // Nursing (MSN). msn-cnl-program-estimated-costs.
      114 => 526,
      // Medicine (non-MD). ms-phd-mme-mca-and-mpa-programs-medicine-estimated-costs.
      100 => 536,
      // Business (MBA). full-time-mba-program-estimated-costs.
      110 => 546,
    ];

    return isset($map[$nid]) ? $map[$nid] : FALSE;
  }

}
