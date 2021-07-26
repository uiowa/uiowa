<?php

namespace Drupal;

use Behat\Behat\Hook\Scope\AfterFeatureScope;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

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
   * @Given I am logged in as a user with role :role
   *
   * Do a one-time login and assign the given role.
   *
   * This is necessary because the profile disables local Drupal accounts
   * making regular Behat login useless. Must be run against the default site.
   */
  public function iAmLoggedInAsUserWithRole($role) {
    $name = "behat_{$role}";
    $user = user_load_by_name($name);

    if (!$user) {
      /** @var UserInterface $user */
      $user = User::create([
        'name' => $name,
        'mail' => 'noreply@default.local.drupal.uiowa.edu',
        'status' => 1,
      ]);

      $user->addRole($role);
      $user->save();
    }

    $reset_url = user_pass_reset_url($user) . '/login';
    $this->getSession()->visit($reset_url);
  }

  /**
   * @Given I click the :arg1 element
   *
   * This is necessary as Behat only supports clicking certain links and buttons
   * out of the box.
   */
  public function iClickTheElement($selector)
  {
    $page = $this->getSession()->getPage();
    $element = $page->find('css', $selector);

    if (empty($element)) {
      throw new \Exception("No html element found for the selector ('$selector')");
    }

    $element->click();
  }

  /**
   * @Given no :menu menu links
   *
   * Delete all menu links in a given menu.
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
   * @Given all unpublished :menu menu links
   *
   * Create some menu links in a given menu.
   */
  public function allUnpublishedMenuLinks($menu) {
    $this->noMenuLinks($menu);

    // @todo: Allow passing this content in.
    $items = [
      'Foo',
      'Bar',
      'Baz',
    ];

    foreach($items as $title) {
      $node = Node::create([
        'type' => 'page',
        'title' => $title,
        'langcode' => 'en',
        'uid' => '1',
        'status' => 0,
      ]);

      $node->save();

      $menu_link = MenuLinkContent::create([
        'title' => $title,
        'link' => ['uri' => 'internal:/node/' . $node->id()],
        'menu_name' => 'main',
        'expanded' => TRUE,
      ]);

      $menu_link->save();
      drupal_flush_all_caches();
    }
  }

  /**
   * @AfterFeature @alerts
   *
   * @param AfterFeatureScope $scope
   */
  public static function alertsTearDown(AfterFeatureScope $scope) {
    \Drupal::configFactory()->getEditable('uiowa_alerts.settings')
      ->set('custom_alert.display', false)
      ->save();
  }
}
