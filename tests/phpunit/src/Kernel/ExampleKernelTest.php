<?php

namespace Sitenow\Tests\PHPUnit\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\Tests\node\Traits\NodeCreationTrait;

/**
 * Class SiteSettingsFormTest.
 *
 * @group kernel
 */
class ExampleKernelTest extends EntityKernelTestBase {
  use NodeCreationTrait;

  /**
   * Disable strict schema checking.
   *
   * @var bool
   */
  protected $strictConfigSchema = FALSE;

  /**
   * Additional modules to enable.
   *
   * {@inheritdoc}
   *
   * @var string[]
   */
  public static $modules = ['node'];

  /**
   * Setup tasks.
   */
  public function setUp() {
    parent::setUp();
    $this->setInstallProfile('sitenow');
    $this->installConfig(['filter']);
    $this->installSchema('node', ['node_access']);
  }

  /**
   * Test front page is prevented from being deleted.
   */
  public function testFrontPageValidation() {
    $title = 'Front page';

    $editor = $this->createUser();
    $editor->addRole('editor');
    $editor->save();

    $node = $this->createNode([
      'title' => $title,
      'type' => 'page',
      'uid' => $editor->id()
    ]);

    $this->assertEquals($title, $node->getTitle());
  }

}
