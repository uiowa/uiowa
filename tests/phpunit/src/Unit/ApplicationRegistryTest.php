<?php

namespace Uiowa\Tests\PHPUnit\Unit;

use Drupal\Tests\UnitTestCase;
use SiteNow\Config\Applications;
use Symfony\Component\Yaml\Yaml;

/**
 * Unit tests for the SiteNow application registry.
 *
 * Covers the registry reader and guards against drift from the legacy
 * blt.yml registry, which must stay in sync until the un-ported BLT
 * consumers (install, transfer, GitCommands) migrate at epic Step 6.
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
  apptwo:
    uuid: uuid-two
    reserved: true
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
   * The SiteNow registry stays in sync with the legacy blt.yml registry.
   *
   * Both must agree until the legacy consumers migrate and the blt.yml block
   * is removed at epic Step 6.
   */
  public function testRegistryMatchesLegacyBltRegistry() {
    $repo = $this->root . '/..';
    $new = Yaml::parseFile("{$repo}/sitenow/applications.yml")['applications'] ?? [];
    $legacy = Yaml::parseFile("{$repo}/blt/blt.yml")['uiowa']['applications'] ?? [];

    $new_map = [];
    foreach ($new as $name => $entry) {
      $new_map[$name] = $entry['uuid'];
    }
    ksort($new_map);
    ksort($legacy);

    $this->assertSame(
      $legacy,
      $new_map,
      'sitenow/applications.yml must match blt.yml uiowa.applications until the legacy registry is removed at Step 6.'
    );
  }

}
