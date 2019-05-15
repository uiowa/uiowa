<?php

namespace Sitenow\Tests\PHPUnit\Functional;

/**
 * Class EditorTest.
 *
 * @group functional
 */
class EditorTest extends ProfileTestBase {

  /**
   * Test editor operations.
   *
   * These are grouped into one method for better performance.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testEditorOperations() {
    $this->drupalLoginOneTime('editor');
    $this->drupalGet('admin/people');
    $this->assertSession()->statusCodeEquals(200);

    $this->drupalGet('admin/people/create');
    $this->assertSession()->statusCodeEquals(403);

    // Test editor cannot access basic site settings.
    $this->drupalGet('admin/config/system/site-information');
    $this->assertSession()->statusCodeEquals(403);
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
