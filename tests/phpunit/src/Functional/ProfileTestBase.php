<?php

namespace Sitenow\Tests\PHPUnit\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Component\Render\FormattableMarkup;

/**
 * Base functional test class that uses the sitenow install profile.
 */
abstract class ProfileTestBase extends BrowserTestBase {
  /**
   * Install profile to use.
   *
   * @var array
   */
  protected $profile = 'sitenow';

  /**
   * Disable strict schema checking.
   *
   * @var bool
   */
  protected $strictConfigSchema = FALSE;

  /**
   * Do a one-time login and assign the given role.
   *
   * This is necessary because the profile disables local Drupal accounts
   * making $this->drupalLogin() useless. Must be run against the default site.
   *
   * @param string $role
   *   The RID to assign.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function drupalLoginOneTime($role = '') {
    $account = $this->drupalCreateUser();
    $link = user_pass_reset_url($account);
    $this->drupalGet($link);
    $this->click('#edit-submit');
    $account->sessionId = $this->getSession()->getCookie($this->getSessionName());
    $this->assertTrue($this->drupalUserIsLoggedIn($account), new FormattableMarkup('User %name successfully logged in.', ['%name' => $account->getAccountName()]));

    if (!empty($role)) {
      $account->addRole($role);
      $account->save();
    }

    $this->loggedInUser = $account;
    $this->container->get('current_user')->setAccount($account);
  }

}
