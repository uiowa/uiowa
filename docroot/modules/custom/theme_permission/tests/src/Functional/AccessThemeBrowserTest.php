<?php

declare(strict_types=1);

namespace Drupal\Tests\theme_permission\Functional;

use Drupal\Core\Url;

/**
 * Administration theme access check.
 *
 * @group theme_permission
 */
class AccessThemeBrowserTest extends ThemePermissionTestBase {

  /**
   * Check if user access to Bartik theme.
   */
  public function testIfAccessThemeBartik(): void {
    $this->userLogin(['administer themes bartik']);
    $this->drupalGet(Url::fromRoute('system.theme_settings_theme', ['theme' => 'bartik']));
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Check if user don't access to Bartik theme.
   */
  public function testIfAccessDeniedThemeBartik(): void {
    $this->userLogin();
    $this->drupalGet(Url::fromRoute('system.theme_settings_theme', ['theme' => 'bartik']));
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Check if the user accesses "Edit Administration theme".
   */
  public function testEditAdminTheme(): void {
    $this->userLogin(['Edit Administration theme']);
    $this->drupalGet(Url::fromRoute('system.themes_page'));
    $this->assertSession()->pageTextContains('Choose "Default theme" to always use the same theme as the rest of the site.');
  }

  /**
   * Check if the user not accesses "Edit Administration theme".
   */
  public function testNotEditAdminTheme(): void {
    $this->userLogin();
    $this->drupalGet(Url::fromRoute('system.themes_page'));
    $this->assertSession()->pageTextNotContains('Choose "Default theme" to always use the same theme as the rest of the site.');
  }

  /**
   * Check if permission is present in permissions page.
   */
  public function testIfPermissionsIsPresent(): void {
    $this->userLogin(['administer permissions']);
    $this->drupalGet('/admin/people/permissions');
    $this->assertSession()->pageTextContains('administer themes bartik');
    $this->assertSession()->pageTextContains('uninstall themes bartik');
    $this->assertSession()->pageTextContains('Edit Administration theme');
  }

}
