<?php

namespace Drupal;

use Behat\Behat\Hook\Scope\AfterFeatureScope;
use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;

/**
 * FeatureContext class defines custom step definitions for Behat.
 */
class FeatureContext extends RawDrupalContext {

  /**
   * Every scenario gets its own context instance.
   *
   * You can also pass arbitrary arguments to the
   * context constructor through behat.yml.
   */
  public function __construct() {

  }

  /**
   * Do a one-time login and assign the given role.
   *
   * @Given I am logged in as a user with role :role
   *
   * This is necessary because the profile disables local Drupal accounts
   * making regular Behat login useless. Must be run against the default site.
   */
  public function iAmLoggedInAsUserWithRole($role) {
    $name = "behat_{$role}";
    $user = user_load_by_name($name);

    if (!$user) {
      /** @var \Drupal\user\Entity\UserInterface $user */
      $user = User::create([
        'name' => $name,
        'mail' => 'noreply@default.uiowa.ddev.site',
        'status' => 1,
      ]);

      $user->addRole($role);
      $user->save();
    }

    $reset_url = user_pass_reset_url($user) . '/login';
    $this->getSession()->visit($reset_url);
  }

  /**
   * Click an element given a selector.
   *
   * @Given I click the :arg1 element
   *
   * This is necessary as Behat only supports clicking certain links and buttons
   * out of the box.
   */
  public function iClickTheElement($selector) {
    $page = $this->getSession()->getPage();
    $element = $page->find('css', $selector);

    if (empty($element)) {
      throw new \Exception("No html element found for the selector ('$selector')");
    }

    $element->click();
  }

  /**
   * Delete all menu links from a given menu.
   *
   * @Given no :menu menu links
   */
  public function noMenuLinks($menu) {
    $mids = \Drupal::entityQuery('menu_link_content')
      ->condition('menu_name', $menu)
      ->execute();

    $controller = \Drupal::entityTypeManager()->getStorage('menu_link_content');
    $entities = $controller->loadMultiple($mids);
    $controller->delete($entities);
  }

  /**
   * Create some menu links in a given menu.
   *
   * @Given :menu menu links to content with :status status
   */
  public function menuLinksWithStatus($menu, $status) {
    $this->noMenuLinks($menu);
    $status = ($status == 'published');

    // @todo Allow passing this content in.
    $items = [
      'Foo',
      'Bar',
      'Baz',
    ];

    foreach ($items as $title) {
      $node = Node::create([
        'type' => 'page',
        'title' => $title,
        'uid' => '1',
      ]);

      if ($status) {
        $node->set('status', TRUE);
        $node->set('moderation_state', 'published');
      }

      $node->save();

      $menu_link = MenuLinkContent::create([
        'title' => $title,
        'link' => ['uri' => 'internal:/node/' . $node->id()],
        'menu_name' => $menu,
        'expanded' => TRUE,
      ]);

      $menu_link->save();
    }
  }

  /**
   * Clear out the alerts settings.
   *
   * @param \Behat\Behat\Hook\Scope\AfterFeatureScope $scope
   *   The scope.
   *
   * @AfterFeature @alerts
   */
  public static function alertsTearDown(AfterFeatureScope $scope) {
    \Drupal::configFactory()->getEditable('uiowa_alerts.settings')
      ->set('custom_alert.display', FALSE)
      ->save();
  }

  /**
   * Clear the cache.
   *
   * @param \Behat\Behat\Hook\Scope\AfterScenarioScope $scope
   *   The scope.
   *
   * @AfterScenario @menu
   */
  public static function menuTearDown(AfterScenarioScope $scope) {
    drupal_flush_all_caches();
  }

}
