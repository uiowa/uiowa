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

    if ($catalog_url = $row->getSourceProperty('field_grad_catalog_url')) {
      $related_links[] = [
        'url' => $catalog_url[0]['url'],
        'title' => $catalog_url[0]['title'],
      ];
    }

    $row->setSourceProperty('custom_related_links', $related_links);

    if ($ref = $row->getSourceProperty('field_grad_costs')) {
      if ($lookup = $this->manualLookup($ref[0]['target_id'])) {
        $row->setSourceProperty('custom_domestic_cost', $lookup);
        $this->logger->info('Swap node @nid for @estimated_cost', [
          '@nid' => $ref[0]['target_id'],
          '@estimated_cost' => $row->getSourceProperty('title'),
        ]);
      }
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function postImport(MigrateImportEvent $event) {
    // @todo Figure out why this event fires multiple times.
    $has_run = $this->state->get('grad_admissions_migrate_post_import', FALSE);

    if ($has_run == FALSE) {
      $migration = $event->getMigration();
      $map = $migration->getIdMap();

      $entity_types = [
        'taxonomy_term' => [
          'colleges' => [
            'description',
          ],
          'degree_types' => [
            'description',
          ],
          'grad_areas_of_study' => [
            'description',
          ],
        ],
        'node' => [
          'area_of_study' => [
            'body',
            'field_area_of_study_requirements',
            'field_area_of_study_deadlines',
            'field_area_of_study_procedures',
            'field_area_of_study_apply',
            'field_area_of_study_contact',
            'field_area_of_study_grad_intro',
          ],
        ],
      ];

      foreach ($entity_types as $entity_type => $bundles) {
        foreach ($bundles as $bundle => $fields) {
          $condition = ($entity_type == 'taxonomy_term') ? 'vid' : 'type';
          $query = $this->entityTypeManager->getStorage($entity_type)->getQuery();

          $ids = $query
            ->condition($condition, $bundle)
            ->execute();

          if ($ids) {
            $controller = $this->entityTypeManager->getStorage($entity_type);
            $entities = $controller->loadMultiple($ids);

            foreach ($entities as $entity) {
              foreach ($fields as $field_name) {
                $document = Html::load($entity->$field_name->value);
                $links = $document->getElementsByTagName('a');

                foreach ($links as $link) {
                  $href = $link->getAttribute('href');

                  if (strpos($href, '/node/') === 0 || stristr($href, 'admissions.uiowa.edu/node/')) {
                    $nid = explode('node/', $href)[1];
                    $lookup = $map->lookupDestinationIds(['nid' => $nid]);

                    // Fallback to a manual map of NIDs provided by customer.
                    if (empty($lookup)) {
                      $lookup = $this->brokenLinkLookup($nid);
                    }

                    // Fix it or log if we don't have a lookup.
                    if (!empty($lookup)) {
                      $destination = $lookup[0][0];
                      $link->setAttribute('href', "/node/{$destination}");
                      $link->parentNode->replaceChild($link, $link);

                      $document->saveHTML();
                      $html = Html::serialize($document);
                      $entity->$field_name->value = $html;
                      $entity->save();
                    }
                    else {
                      $this->logger->notice('Cannot replace internal link @link in field @field on @bundle @aos.', [
                        '@link' => $href,
                        '@field' => $field_name,
                        '@bundle' => $bundle,
                        '@aos' => $entity->label(),
                      ]);
                    }
                  }
                }
              }
            }
          }
        }
      }

      $this->state->set('grad_admissions_migrate_post_import', TRUE);
    }
  }

  /**
   * Look up a node URL from a manually created source -> destination map.
   *
   * @param int $nid
   *   The NID to look up.
   *
   * @return array
   *   A simulated array of arrays similar to the migrate idMap.
   */
  protected function brokenLinkLookup($nid) {
    $lookup = [];

    $map = [
      170 => 586,
      179 => 131,
      578 => 201,
      74 => 136,
      76 => 596,
    ];

    if (isset($map[$nid])) {
      $lookup = [
        [$map[$nid]],
      ];
    }

    return $lookup;
  }

  /**
   * Return the destination given an estimated cost NID on the old site.
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
      // Public health (not MPH).
      // graduate-programs-public-health-estimated-costs.
      103 => 336,
      // Sustainable Water Development.
      // sustainable-water-development-certificate-estimated-costs.
      931 => 346,
      // Oral Science Program. oral-science-program-estimated-costs.
      115 => 356,
      // Applied Math & Computational Sciences.
      // applied-math-computational-sciences-estimated-costs.
      105 => 366,
      // LIS. library-information-science-estimated-costs.
      109 => 376,
      // Second Language Acquisition.
      // second-language-acquisition-estimated-costs.
      119 => 386,
      // Grad Programs in Pharmacy.
      // graduate-programs-pharmacy-estimated-costs.
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
      // Speech Pathology & Audiology.
      // speech-pathology-and-audiology-ma-or-aud-estimated-costs.
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
      // Medicine (non-MD).
      // ms-phd-mme-mca-and-mpa-programs-medicine-estimated-costs.
      100 => 536,
      // Business (MBA). full-time-mba-program-estimated-costs.
      110 => 546,
    ];

    return $map[$nid] ?? FALSE;
  }

}
