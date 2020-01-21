<?php

namespace Drupal\Tests\views_tree\Kernel\Plugin\views\style;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\Tests\field\Traits\EntityReferenceTestTrait;
use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\views\Tests\ViewTestData;

/**
 * Base class for testing tree style plugins.
 */
abstract class TreeTestBase extends ViewsKernelTestBase {

  use EntityReferenceTestTrait;

  /**
   * Parent entities.
   *
   * @var \Drupal\entity_test\Entity\EntityTest[]
   */
  protected $parents;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'entity_test',
    'field',
    'views_tree',
    'views_tree_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE) {
    parent::setUp(FALSE);
    ViewTestData::createTestViews(get_class($this), ['views_tree_test']);

    $this->installEntitySchema('entity_test');

    // Create reference from entity_test to entity_test.
    $this->createEntityReferenceField('entity_test', 'entity_test', 'field_test_parent', 'field_test_parent', 'entity_test', 'default', [], FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);

    $this->createHierarchy();
  }

  /**
   * Creates a hierarchy of entity_test entities.
   */
  protected function createHierarchy() {
    // Create 3 parent nodes.
    foreach (range(1, 3) as $i) {
      $entity = EntityTest::create(['name' => 'parent ' . $i]);
      $entity->save();
      $this->parents[$entity->id()] = $entity;

      // Add 3 child entities for each parent.
      foreach (range(1, 3) as $j) {
        $child = EntityTest::create([
          'name' => 'child ' . $j . ' (parent ' . $i . ')',
          'field_test_parent' => ['target_id' => $entity->id()],
        ]);
        $child->save();

        // For parent 2, child 1, add 3 grandchildren.
        if ($i === 2 && $j === 1) {
          foreach (range(1, 3) as $k) {
            $grand_child = EntityTest::create([
              'name' => 'grand child ' . $k . ' (c ' . $j . ', p ' . $i . ')',
              'field_test_parent' => ['target_id' => $child->id()],
            ]);
            $grand_child->save();
          }
        }
      }
    }
  }

}
