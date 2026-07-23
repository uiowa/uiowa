<?php

namespace Uiowa\Tests\PHPUnit\Unit;

use Drupal\Tests\UnitTestCase;
use SiteNow\Config\Applications;
use Symfony\Component\Yaml\Yaml;

/**
 * Unit tests for the SiteNow application registry.
 *
 * @group unit
 */
class ApplicationRegistryTest extends UnitTestCase {

  /**
   * Build a reader over a controlled fixture registry.
   */
  private function fixtureReader(): Applications {
    $yaml = <<<YAML
applications:
  appone:
    uuid: uuid-one
    remote: 'appone@example.com:appone.git'
  apptwo:
    uuid: uuid-two
    reserved: true
    remote: 'apptwo@example.com:apptwo.git'
run_first:
  - first.uiowa.edu
  - second.uiowa.edu
YAML;
    $path = tempnam(sys_get_temp_dir(), 'sitenow_apps_');
    file_put_contents($path, $yaml);
    $reader = new Applications($path);
    unlink($path);
    return $reader;
  }

  /**
   * Build a reader over a registry with no run_first block.
   */
  private function readerWithoutRunFirst(): Applications {
    $yaml = <<<YAML
applications:
  appone:
    uuid: uuid-one
YAML;
    $path = tempnam(sys_get_temp_dir(), 'sitenow_apps_');
    file_put_contents($path, $yaml);
    $reader = new Applications($path);
    unlink($path);
    return $reader;
  }

  /**
   * The reader exposes every registered application.
   */
  public function testAllReturnsEntriesKeyedByName() {
    $this->assertSame(['appone', 'apptwo'], $this->fixtureReader()->names());
  }

  /**
   * UUID lookup resolves registered applications and NULL otherwise.
   */
  public function testUuidLookup() {
    $reader = $this->fixtureReader();
    $this->assertSame('uuid-one', $reader->uuid('appone'));
    $this->assertNull($reader->uuid('nope'));
  }

  /**
   * Git remote lookup resolves registered applications and NULL otherwise.
   */
  public function testRemoteLookup() {
    $reader = $this->fixtureReader();
    $this->assertSame('appone@example.com:appone.git', $reader->remote('appone'));
    $this->assertNull($reader->remote('nope'));
  }

  /**
   * The reserved flag is read per application.
   */
  public function testIsReserved() {
    $reader = $this->fixtureReader();
    $this->assertTrue($reader->isReserved('apptwo'));
    $this->assertFalse($reader->isReserved('appone'));
  }

  /**
   * Automatic selection excludes reserved applications.
   */
  public function testAutoSelectableExcludesReserved() {
    $this->assertSame(['appone'], array_keys($this->fixtureReader()->autoSelectable()));
  }

  /**
   * The run_first list is read in its configured order.
   */
  public function testRunFirstReturnsConfiguredOrder() {
    $this->assertSame(
      ['first.uiowa.edu', 'second.uiowa.edu'],
      $this->fixtureReader()->runFirst()
    );
  }

  /**
   * A registry with no run_first block yields an empty list.
   */
  public function testRunFirstEmptyWhenAbsent() {
    $this->assertSame([], $this->readerWithoutRunFirst()->runFirst());
  }

}
