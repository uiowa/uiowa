<?php

namespace Uiowa\Tests\PHPUnit\Unit;

use Drupal\Tests\UnitTestCase;
use SiteNow\Config\Splits;

/**
 * Unit tests for reading the repository's config split definitions.
 *
 * @group unit
 */
class SplitsTest extends UnitTestCase {

  /**
   * The fixture repository root.
   */
  private string $fixture;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->fixture = sys_get_temp_dir() . '/splits-test-' . uniqid();
    mkdir("{$this->fixture}/config/default", 0777, TRUE);
    $this->define('config/default', 'thesis_defense', '../config/features/thesis_defense');
    $this->define('config/default', 'event', '../config/features/event/');
    $this->define('config/default', 'local', '../config/envs/local');
    foreach (['grad.uiowa.edu', 'admissions.uiowa.edu'] as $host) {
      mkdir("{$this->fixture}/config/sites/{$host}", 0777, TRUE);
      $this->define("config/sites/{$host}", 'site', "../config/sites/{$host}");
    }
    // A site folder without a site split definition.
    mkdir("{$this->fixture}/config/sites/nosplit.uiowa.edu", 0777, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    exec('rm -rf ' . escapeshellarg($this->fixture));
    parent::tearDown();
  }

  /**
   * Write a split definition into the fixture.
   */
  private function define(string $dir, string $id, string $folder): void {
    file_put_contents("{$this->fixture}/{$dir}/config_split.config_split.{$id}.yml", "id: {$id}\nfolder: {$folder}\n");
  }

  /**
   * Feature splits are those exporting under config/features, sorted by ID.
   */
  public function testFeaturesExcludeEnvironmentalSplits(): void {
    $this->assertSame([
      'event' => 'config/features/event',
      'thesis_defense' => 'config/features/thesis_defense',
    ], (new Splits($this->fixture))->features());
  }

  /**
   * Site splits are the hosts with a site split definition, sorted by host.
   */
  public function testSitesAreHostsWithSiteSplit(): void {
    $this->assertSame([
      'admissions.uiowa.edu' => 'config/sites/admissions.uiowa.edu',
      'grad.uiowa.edu' => 'config/sites/grad.uiowa.edu',
    ], (new Splits($this->fixture))->sites());
  }

  /**
   * A split's dependencies are activated before it.
   */
  public function testActivationOrderPutsDependenciesFirst(): void {
    $splits = new Splits($this->fixture);
    $this->assertSame(['sitenow_v2', 'p2lb'], $splits->activationOrder('p2lb'));
    $this->assertSame(['event'], $splits->activationOrder('event'));
  }

}
