<?php

namespace Drupal\Tests\sitenow\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\Tests\node\Traits\NodeCreationTrait;

/**
 * Class SiteSettingsFormTest.
 *
 * @group kernel
 */
class PermissionsTest extends EntityKernelTestBase {
  use NodeCreationTrait;

  /**
   * Additional modules to enable.
   *
   * {@inheritdoc}
   *
   * @var string[]
   */
  public static $modules = ['node', 'filter'];

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
   * Test node creation (example).
   */
  public function testNodeCreation() {
    $title = 'Front page';

    $editor = $this->createUser();
    $editor->addRole('editor');
    $editor->save();

    $node = $this->createNode([
      'title' => $title,
      'type' => 'page',
      'uid' => $editor->id(),
    ]);

    $this->assertEquals($title, $node->getTitle());
  }

  /**
   * Test editor permissions.
   */
  public function testEditorPermissions() {
    $path = $this->getDrupalRoot();
    $sync = new FileStorage($path . '/../config/default');
    $data = $sync->read('user.role.editor');

    $permissions = array_flip($data['permissions']);

    $this->assertArrayHasKey('access users overview', $permissions);
    $this->assertArrayNotHasKey('create users', $permissions);
    $this->assertArrayNotHasKey('administer users', $permissions);
    $this->assertArrayNotHasKey('administer basic site settings', $permissions);
  }

  /**
   * Test webmaster permissions.
   */
  public function testWebmasterPermission() {
    $path = $this->getDrupalRoot();
    $sync = new FileStorage($path . '/../config/default');
    $data = $sync->read('user.role.webmaster');

    $permissions = array_flip($data['permissions']);

    $this->assertArrayHasKey('access users overview', $permissions);
    $this->assertArrayHasKey('create users', $permissions);
    $this->assertArrayNotHasKey('administer users', $permissions);
    $this->assertArrayHasKey('administer basic site settings', $permissions);
  }

}
