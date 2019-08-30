<?php

namespace Drupal\Tests\sitenow\Unit;

use Drupal\Tests\UnitTestCase;

/**
 * Basic file system tests.
 *
 * @group unit
 */
class FilesystemTest extends UnitTestCase {

  /**
   * Test that the robots.txt file does not exist.
   */
  public function testRobotsTxtDoesNotExist() {
    $this->assertFileNotExists(__DIR__ . '/../../../../docroot/robots.txt');
  }

}
