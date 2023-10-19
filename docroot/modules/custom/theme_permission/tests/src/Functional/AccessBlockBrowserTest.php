<?php

declare(strict_types=1);

namespace Drupal\Tests\theme_permission\Functional;

/**
 * Administration theme access check.
 *
 * @group theme_permission
 */
class AccessBlockBrowserTest extends ThemePermissionTestBase {

  /**
   * Check if user access to Bartik blocks configuration.
   */
  public function testIfAccessThemeBartik() {
    $this->userLogin(['administer themes bartik']);
    $this->drupalGet('/admin/structure/block/list/bartik');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Check if user don't access to Bartik blocks configuration.
   */
  public function testIfAccessDeniedThemeBartik() {
    $this->userLogin();
    $this->drupalGet("/admin/structure/block/list/bartik");
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Check if block list contain seven url.
   */
  public function testIfShowSeven() {
    $this->userLogin(
      [
        'administer themes bartik',
        'administer themes seven',
      ]
    );
    $this->drupalGet('/admin/structure/block');
    $this->assertNotEmpty($this->getSession()->getPage()->find('xpath', '//a[contains(@href, "/admin/structure/block/list/seven")]'));
  }

  /**
   * Check if block list don't contain seven url.
   */
  public function testIfNotShowSeven() {
    $this->userLogin();
    $this->drupalGet("/admin/structure/block");
    $this->assertEmpty($this->getSession()->getPage()->find('xpath', '//a[contains(@href, "/admin/structure/block/list/seven")]'));
  }

}
