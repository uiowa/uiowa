<?php

namespace Drupal\admissions_migrate\Plugin\migrate\source;

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
 *  id = "d7_admissions_aos",
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

    if ($dept_url = $row->getSourceProperty('field_dept_url')) {
      $related_links[] = [
        'url' => $dept_url[0]['url'],
        'title' => $dept_url[0]['title'],
      ];
    }

    if ($catalog_url = $row->getSourceProperty('field_catalog_url')) {
      $related_links[] = [
        'url' => $catalog_url[0]['url'],
        'title' => $catalog_url[0]['title'],
      ];
    }

    $row->setSourceProperty('custom_related_links', $related_links);

    // We want the default alias but also want to leave 'generate' unchecked.
    $alias = $this->aliasCleaner->cleanString($row->getSourceProperty('title'));
    $row->setSourceProperty('custom_alias', "/academics/{$alias}");

    // We map the first alt title to title and title to alt title if different.
    $title = $row->getSourceProperty('title');
    $alt = $row->getSourceProperty('field_alt_names')[0]['value'];

    if ($title != $alt) {
      $row->setSourceProperty('custom_alt_title', $title);
    }
    else {
      $row->setSourceProperty('custom_alt_title', NULL);
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function postImportProcess(MigrateImportEvent $event) {
    // @todo Figure out why this event fires multiple times.
    $has_run = $this->state->get('admissions_migrate_post_import', FALSE);

    if ($has_run == FALSE) {
      $migration = $event->getMigration();
      $map = $migration->getIdMap();

      $entity_types = [
        'taxonomy_term' => [
          'academic_groups' => [
            'description',
          ],
          'colleges' => [
            'description',
          ],
        ],
        'node' => [
          'transfer_tips' => [
            'body',
          ],
          'area_of_study' => [
            'body',
            'field_area_of_study_subtitle',
            'field_area_of_study_why',
            'field_area_of_study_course_work',
            'field_area_of_study_requirement',
            'field_area_of_study_opportunity',
            'field_area_of_study_career',
            'field_area_of_study_scholarship',
          ],
        ],
        'paragraph' => [
          'admissions_requirement' => [
            'field_ar_intro',
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
                      $lookup = $this->manualLookup($nid);
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

      $this->state->set('admissions_migrate_post_import', TRUE);
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
  protected function manualLookup($nid) {
    $lookup = [];

    $map = [
      79 => 71,
      53 => 206,
      54 => 151,
      77 => 61,
      195 => 156,
      196 => 216,
      147 => 211,
      146 => 166,
      214 => 171,
      215 => 201,
      137 => 21,
      244 => 161,
      291 => 221,
      294 => 56,
      72 => 61,
      93681 => 71,
      71 => 286,
    ];

    if (isset($map[$nid])) {
      $lookup = [
        [$map[$nid]],
      ];
    }

    return $lookup;
  }

}
