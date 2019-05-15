<?php

namespace Sitenow\Tests\PHPUnit\Functional;

/**
 * Class WebmasterTest.
 *
 * @group functional
 */
class WebmasterTest extends ProfileTestBase {

  /**
   * Test webmaster operations.
   *
   * These are grouped into one method for better performance.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testWebmasterOperations() {
    $this->drupalLoginOneTime('webmaster');
    $this->drupalGet('admin/people');
    $this->assertSession()->statusCodeEquals(200);

    // Test that administrators are not listed on people overview page.
    $this->assertSession()->elementTextNotContains('css', '.views-table', 'Administrator');

    // Test webmaster cannot escalate role through bulk user view.
    $this->assertSession()->elementTextNotContains('css', '#edit-action', 'Add the Administrator role to the selected user(s)');

    // Test webmaster can access user register form.
    $this->drupalGet('admin/people/create');
    $this->assertSession()->statusCodeEquals(200);

    // Test webmaster cannot access a user's default path on create.
    $this->assertSession()->elementNotExists('css', '#edit-path-0-alias');

    // Test webmaster can add an editor.
    $edit = [
      'edit-name' => 'editor',
      'edit-roles-editor' => TRUE,
    ];

    $this->submitForm($edit, 'Create new account', 'user-register-form');
    $this->assertSession()->elementTextContains('css', '.messages--status', 'Created a new user account for editor. No email has been sent.');

    // Test webmaster can add another webmaster.
    $edit = [
      'edit-name' => 'webmaster deux',
      'edit-roles-webmaster' => TRUE,
    ];

    $this->submitForm($edit, 'Create new account', 'user-register-form');
    $this->assertSession()->elementTextContains('css', '.messages--status', 'Created a new user account for webmaster deux. No email has been sent.');

    // Test Webmaster can edit editor.
    $account = user_load_by_name('editor');
    $id = $account->id();
    $this->drupalGet("user/{$id}/edit");
    $this->assertSession()->statusCodeEquals(200);

    $edit = [
      'edit-status-0' => TRUE,
    ];

    $this->submitForm($edit, 'Save', 'user-form');
    $this->assertSession()->elementTextContains('css', '.messages--status', 'The changes have been saved.');

    // Test webmaster can access basic site settings only.
    $this->drupalGet('admin/config/system/site-information');
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet('admin/config/media/file-system');
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalGet('admin/config/system/cron');
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalGet('admin/config/development/performance');
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalGet('admin/config/development/logging');
    $this->assertSession()->statusCodeEquals(403);
  }

}
