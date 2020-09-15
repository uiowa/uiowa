<?php

namespace Drupal;

use Behat\Behat\Hook\Scope\AfterFeatureScope;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Behat\Behat\Context\SnippetAcceptingContext;
use Drupal\user\Entity\User;

/**
 * FeatureContext class defines custom step definitions for Behat.
 */
class FeatureContext extends RawDrupalContext implements SnippetAcceptingContext {

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
      /** @var \Drupal\user\Entity\UserInterface $user */
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
  public function iClickTheElement($selector) {
    $page = $this->getSession()->getPage();
    $element = $page->find('css', $selector);

    if (empty($element)) {
      throw new \Exception("No html element found for the selector ('$selector')");
    }

    $element->click();
  }

  /**
   * @AfterFeature @alerts
   *
   * @param \Behat\Behat\Hook\Scope\AfterFeatureScope $scope
   */
  public static function alertsTearDown(AfterFeatureScope $scope) {
    \Drupal::configFactory()->getEditable('uiowa_alerts.settings')
      ->set('custom_alert.display', FALSE)
      ->set('hawk_alert.source', 'https://emergency.uiowa.edu/api/active.json')
      ->save();
  }

  /**
   * @AfterFeature @events
   *
   * @param \Behat\Behat\Hook\Scope\AfterFeatureScope $scope
   */
  public static function eventsTearDown(AfterFeatureScope $scope) {
    $query = \Drupal::entityQuery('node');

    $ids = $query->condition('title', 'Events')
      ->condition('status', 1)
      ->execute();

    if ($ids) {
      $storage_handler = \Drupal::entityTypeManager()->getStorage('node');
      $entities = $storage_handler->loadMultiple($ids);
      $storage_handler->delete($entities);
    }
  }

}
