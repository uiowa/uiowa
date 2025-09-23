<?php

namespace Drupal\Tests\policy_core\Kernel;

use Drupal\policy_core\Controller\PDFContentController;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\user\Entity\User;

class PDFContentControllerTest extends KernelTestBase {

  protected static $modules = [
    'system',
    'user',
    'node',
    'menu_link_content',
    'link',
  ];

  protected function setUp(): void {
    parent::setUp();

    // Install db schemas for entities used.
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('menu_link_content');
  }

  public function testReturnedMenuOrder() {
    $user = User::create([
      'name' => 'rusty',
      'mail' => 'rusty@uiowa.edu',
      'status' => 1,
      'roles' => ['webmaster'],
    ]);
    $user->save();

    // Set the current user for the menu tree call access.
    \Drupal::currentUser()->setAccount($user);

    $node1 = Node::create(['type' => 'page', 'title' => 'Node 1', 'uid' => $user->id()]);
    $node1->save();
    $node2 = Node::create(['type' => 'page', 'title' => 'Node 2', 'uid' => $user->id()]);
    $node2->save();
    $node3 = Node::create(['type' => 'page', 'title' => 'Node 3', 'uid' => $user->id(), 'status' => 0]);
    $node3->save();
    $node4 = Node::create(['type' => 'page', 'title' => 'Node 4', 'uid' => $user->id()]);
    $node4->save();

    // Create menu links with different levels, weights, states, creation order.
    $link1 = MenuLinkContent::create([
      'menu_name' => 'main',
      'link' => ['uri' => 'internal:/node/' . $node1->id()],
    ]);
    $link1->save();

    // Get the UUID of the created link to use as parent.
    $parent_uuid = $link1->uuid();

    $link2 = MenuLinkContent::create([
      'menu_name' => 'main',
      'link' => ['uri' => 'internal:/node/' . $node2->id()],
      'weight' => 10,
      'parent' => 'menu_link_content:' . $parent_uuid,
    ]);
    $link2->save();

    $link_external = MenuLinkContent::create([
      'menu_name' => 'main',
      'link' => ['uri' => 'https://example.com'],
      'weight' => -10,
      'parent' => 'menu_link_content:' . $parent_uuid,
    ]);
    $link_external->save();

    $link3 = MenuLinkContent::create([
      'menu_name' => 'main',
      'link' => ['uri' => 'internal:/node/' . $node3->id()],
      'weight' => 0,
      'parent' => 'menu_link_content:' . $parent_uuid,
    ]);
    $link3->save();

    $link4 = MenuLinkContent::create([
      'menu_name' => 'main',
      'link' => ['uri' => 'internal:/node/' . $node4->id()],
      'weight' => 0,
      'parent' => 'menu_link_content:' . $parent_uuid,
    ]);
    $link4->save();

    $results = PDFContentController::getNodesByMenuOrder('main', 'page', 'menu_link_content:' . $parent_uuid);

    $this->assertCount(2, $results, 'Expected two nodes back as first node is removed and unpublished is not included.');
    $this->assertEquals($node4->id(), $results[0]['node']->id(), 'Expected node 4 to be first due to weight.');
    $this->assertEquals($node2->id(), $results[1]['node']->id(), 'Expected node 2 to be second due to weight.');

  }

}
