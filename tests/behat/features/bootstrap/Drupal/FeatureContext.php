<?php

namespace Drupal;

use Drupal\DrupalExtension\Context\RawDrupalContext;
use Behat\Behat\Context\SnippetAcceptingContext;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

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

}
