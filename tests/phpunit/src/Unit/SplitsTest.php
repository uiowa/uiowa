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
  private function define(string $dir, string $id, string $folder, array $enforced = []): void {
    $yaml = "id: {$id}\nfolder: {$folder}\n";
    if ($enforced) {
      $yaml .= "dependencies:\n  enforced:\n    config:\n" . implode('', array_map(fn ($name) => "      - {$name}\n", $enforced));
    }
    file_put_contents("{$this->fixture}/{$dir}/config_split.config_split.{$id}.yml", $yaml);
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
   * Splits a definition enforces a dependency on are activated before it.
   */
  public function testActivationOrderPutsDependenciesFirst(): void {
    $this->define('config/default', 'sitenow_v2', '../config/features/sitenow_v2');
    $this->define('config/default', 'p2lb', '../config/features/p2lb', ['config_split.config_split.sitenow_v2', 'node.type.page']);
    $splits = new Splits($this->fixture);
    $this->assertSame(['sitenow_v2', 'p2lb'], $splits->activationOrder('p2lb'));
    $this->assertSame(['event'], $splits->activationOrder('event'));
  }

  /**
   * A dependency cycle ends.
   */
  public function testActivationOrderStopsAtCycles(): void {
    $this->define('config/default', 'first', '../config/features/first', ['config_split.config_split.second']);
    $this->define('config/default', 'second', '../config/features/second', ['config_split.config_split.first']);
    $this->assertSame(['second', 'first'], (new Splits($this->fixture))->activationOrder('first'));
  }

}
