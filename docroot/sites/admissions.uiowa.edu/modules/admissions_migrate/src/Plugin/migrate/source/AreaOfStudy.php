<?php

namespace Drupal\admissions_migrate\Plugin\migrate\source;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\State\StateInterface;
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
   * Prepare row used for altering source data prior to its insertion.
   *
   * @throws \Drupal\migrate\MigrateException
   */
  public function prepareRow(Row $row) {
    $nid = $row->getSourceProperty('nid');

    // Get Field API field values.
    foreach ($this->getFields('node', 'undergraduate_majors_programs') as $field_name => $field) {
      $row->setSourceProperty($field_name, $this->getFieldValues('node', $field_name, $nid));
    }

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

    // Call the parent prepareRow.
    return parent::prepareRow($row);
  }

  /**
   * Report possible fields to check for broken links after migration.
   */
  public function postImportProcess() {
    $fields = [
      'body',
      'field_area_of_study_subtitle',
      'field_area_of_study_why',
      'field_area_of_study_course_work',
      'field_area_of_study_requirement',
      'field_area_of_study_transfer',
      'field_area_of_study_intl',
      'field_area_of_study_opportunity',
      'field_area_of_study_career',
      'field_area_of_study_scholarship',
    ];

    $query = $this->entityTypeManager->getStorage('node')->getQuery();

    $ids = $query
      ->condition('type', 'area_of_study')
      ->execute();

    if ($ids) {
      $controller = $this->entityTypeManager->getStorage('node');
      $entities = $controller->loadMultiple($ids);

      foreach ($entities as $entity) {
        foreach ($fields as $field) {
          $html = Html::load($entity->$field->getString());
          $links = $html->getElementsByTagName('a');

          foreach ($links as $link) {
            $href = $link->getAttribute('href');

            if (strpos($href, '/node/') === 0 || stristr($href, 'admissions.uiowa.edu/node/')) {
              $this->logger->notice('Internal link @link detected in @field on @aos.', [
                '@link' => $href,
                '@field' => $field,
                '@aos' => $entity->label(),
              ]);
            }
          }
        }
      }
    }

    return parent::postImportProcess();
  }

}
