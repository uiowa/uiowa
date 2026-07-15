<?php

namespace Uiowa\Tests\PHPUnit\Unit;

use Drupal\Tests\UnitTestCase;
use SiteNow\Command\DeployUpdateCommand;
use SiteNow\Command\SiteUpdateCommand;

/**
 * Unit tests for the deploy:update command's per-site classification.
 *
 * Covers the pure logic that turns a parallel joblog into an updated /
 * skipped / config-mismatch / failed summary, and the run_first ordering
 * that moves priority sites to the front. No Acquia, drush, or GNU parallel
 * access.
 *
 * @group unit
 */
class DeployUpdateTest extends UnitTestCase {

  /**
   * Temporary paths to remove after each test.
   *
   * @var string[]
   */
  private array $cleanup = [];

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    foreach ($this->cleanup as $path) {
      $this->removeRecursive($path);
    }
    parent::tearDown();
  }

  /**
   * A command instance exposing the protected classification helpers.
   */
  private function command(string $repoRoot = ''): DeployUpdateCommand {
    return new class($repoRoot) extends DeployUpdateCommand {

      public function pubClassifyJoblog(string $joblog): array {
        return $this->classifyJoblog($joblog);
      }

      public function pubRunFirstOrder(array $sites): array {
        return $this->runFirstOrder($sites);
      }

    };
  }

  /**
   * Write a GNU parallel joblog fixture and return its path.
   *
   * @param array $rows
   *   Each row: [exitval, site]. A header row is prepended automatically.
   */
  private function joblog(array $rows): string {
    $lines = ["Seq\tHost\tStarttime\tJobRuntime\tSend\tReceive\tExitval\tSignal\tCommand"];
    foreach ($rows as $i => [$exit, $site]) {
      $seq = $i + 1;
      $lines[] = "{$seq}\t:\t0\t0\t0\t0\t{$exit}\t0\t/repo/sn site:update {$site}";
    }
    $path = tempnam(sys_get_temp_dir(), 'sn_joblog_');
    file_put_contents($path, implode("\n", $lines) . "\n");
    $this->cleanup[] = $path;
    return $path;
  }

  /**
   * Build a fixture repo root carrying a run_first registry.
   *
   * @param string[] $runFirst
   *   The run_first site list to write into sitenow/applications.yml.
   */
  private function fixtureRepo(array $runFirst): string {
    $root = sys_get_temp_dir() . '/sn_repo_' . uniqid();
    mkdir("{$root}/sitenow", 0777, TRUE);
    $list = implode('', array_map(fn($s) => "  - {$s}\n", $runFirst));
    $yaml = "applications:\n  appone:\n    uuid: uuid-one\nrun_first:\n{$list}";
    file_put_contents("{$root}/sitenow/applications.yml", $yaml);
    $this->cleanup[] = $root;
    return $root;
  }

  /**
   * Recursively remove a file or directory.
   */
  private function removeRecursive(string $path): void {
    if (is_dir($path)) {
      foreach (array_diff(scandir($path), ['.', '..']) as $entry) {
        $this->removeRecursive("{$path}/{$entry}");
      }
      @rmdir($path);
    }
    elseif (is_file($path)) {
      @unlink($path);
    }
  }

  // --- Joblog classification --------------------------------------------------

  /**
   * Each exit code maps to its outcome tier.
   */
  public function testClassifyJoblogSortsByExitCode() {
    $joblog = $this->joblog([
      [0, 'updated.uiowa.edu'],
      [SiteUpdateCommand::SKIPPED, 'skipped.uiowa.edu'],
      [SiteUpdateCommand::CONFIG_MISMATCH, 'mismatch.uiowa.edu'],
      [1, 'failed.uiowa.edu'],
    ]);

    $summary = $this->command()->pubClassifyJoblog($joblog);

    $this->assertSame(1, $summary['updated']);
    $this->assertSame(1, $summary['skipped']);
    $this->assertSame(['mismatch.uiowa.edu'], $summary['mismatch']);
    $this->assertSame(['failed.uiowa.edu'], $summary['failed']);
  }

  /**
   * Multiple sites accumulate into the counts and name lists.
   */
  public function testClassifyJoblogAccumulates() {
    $joblog = $this->joblog([
      [0, 'a.uiowa.edu'],
      [0, 'b.uiowa.edu'],
      [SiteUpdateCommand::SKIPPED, 'c.uiowa.edu'],
      [1, 'd.uiowa.edu'],
      [137, 'e.uiowa.edu'],
    ]);

    $summary = $this->command()->pubClassifyJoblog($joblog);

    $this->assertSame(2, $summary['updated']);
    $this->assertSame(1, $summary['skipped']);
    $this->assertSame([], $summary['mismatch']);
    $this->assertSame(['d.uiowa.edu', 'e.uiowa.edu'], $summary['failed']);
  }

  /**
   * A missing joblog yields an empty summary rather than an error.
   */
  public function testClassifyJoblogMissingFile() {
    $summary = $this->command()->pubClassifyJoblog(sys_get_temp_dir() . '/does-not-exist-' . uniqid());

    $this->assertSame(
      ['updated' => 0, 'skipped' => 0, 'mismatch' => [], 'failed' => []],
      $summary
    );
  }

  /**
   * The header row and any malformed rows are ignored.
   */
  public function testClassifyJoblogSkipsHeaderAndMalformedRows() {
    $path = tempnam(sys_get_temp_dir(), 'sn_joblog_');
    $this->cleanup[] = $path;
    file_put_contents($path, implode("\n", [
      "Seq\tHost\tStarttime\tJobRuntime\tSend\tReceive\tExitval\tSignal\tCommand",
      "1\t:\t0\t0\t0\t0\t0\t0\t/repo/sn site:update good.uiowa.edu",
      "malformed row without enough columns",
      "2\t:\t0\t0\t0\t0\t1\t0\t/repo/sn site:update bad.uiowa.edu",
    ]) . "\n");

    $summary = $this->command()->pubClassifyJoblog($path);

    $this->assertSame(1, $summary['updated']);
    $this->assertSame(['bad.uiowa.edu'], $summary['failed']);
  }

  // --- run_first ordering -----------------------------------------------------

  /**
   * Priority sites move to the front in configured order; the rest follow.
   */
  public function testRunFirstOrderMovesPrioritySitesToFront() {
    $repo = $this->fixtureRepo(['rf1.uiowa.edu', 'rf2.uiowa.edu']);
    $sites = [
      'a.uiowa.edu',
      'rf2.uiowa.edu',
      'b.uiowa.edu',
      'rf1.uiowa.edu',
      'c.uiowa.edu',
    ];

    $ordered = $this->command($repo)->pubRunFirstOrder($sites);

    $this->assertSame(
      [
        'rf1.uiowa.edu',
        'rf2.uiowa.edu',
        'a.uiowa.edu',
        'b.uiowa.edu',
        'c.uiowa.edu',
      ],
      $ordered
    );
  }

  /**
   * A run_first entry absent from the site list is ignored.
   */
  public function testRunFirstOrderIgnoresAbsentEntries() {
    $repo = $this->fixtureRepo(['rf1.uiowa.edu', 'absent.uiowa.edu']);
    $sites = ['a.uiowa.edu', 'rf1.uiowa.edu', 'b.uiowa.edu'];

    $ordered = $this->command($repo)->pubRunFirstOrder($sites);

    $this->assertSame(['rf1.uiowa.edu', 'a.uiowa.edu', 'b.uiowa.edu'], $ordered);
  }

  /**
   * With no run_first configured the site list is returned unchanged.
   */
  public function testRunFirstOrderNoPrioritySites() {
    $repo = $this->fixtureRepo([]);
    $sites = ['a.uiowa.edu', 'b.uiowa.edu', 'c.uiowa.edu'];

    $ordered = $this->command($repo)->pubRunFirstOrder($sites);

    $this->assertSame($sites, $ordered);
  }

}
