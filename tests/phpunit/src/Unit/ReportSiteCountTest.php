<?php

namespace Uiowa\Tests\PHPUnit\Unit;

use Drupal\Tests\UnitTestCase;
use SiteNow\Command\ReportSiteCountCommand;

/**
 * Unit tests for the site count report's tallying and remote script.
 *
 * The tally() method turns one application's per-site drush results into a
 * total/v2/v3 breakdown. The key behavior under test: a site that fails to
 * answer is named and excluded from the v2/v3 split rather than silently
 * counting as v3 or collapsing the whole app to a 0 count.
 *
 * @group unit
 */
class ReportSiteCountTest extends UnitTestCase {

  /**
   * A subclass exposing the protected tally() and the remote script.
   */
  private function command(): ReportSiteCountCommand {
    return new class extends ReportSiteCountCommand {

      /**
       * {@inheritdoc}
       */
      public function count(array $domains, array $results): array {
        return $this->tally($domains, $results);
      }

      /**
       * {@inheritdoc}
       */
      public function script(): string {
        return $this->splitStatusScript();
      }

    };
  }

  /**
   * Build a per-site result array as FleetRunner::run() would return it.
   *
   * @param int $exit
   *   The drush exit code.
   * @param string $output
   *   The drush stdout.
   *
   * @return array{exit: int, output: string, error: string}
   *   The result.
   */
  private function result(int $exit, string $output = ''): array {
    return ['exit' => $exit, 'output' => $output, 'error' => ''];
  }

  /**
   * A clean run splits sites into v2/v3 with none unreachable.
   */
  public function testCleanRunSplitsSites(): void {
    $domains = ['a.example.edu', 'b.example.edu', 'c.example.edu'];
    $results = [
      'a.example.edu' => $this->result(0, '1'),
      'b.example.edu' => $this->result(0, '0'),
      'c.example.edu' => $this->result(0, '0'),
    ];

    $this->assertSame([
      'total' => 3,
      'reachable' => 3,
      'v2' => 1,
      'v3' => 2,
      'v2_sites' => ['a.example.edu'],
      'failed' => [],
    ], $this->command()->count($domains, $results));
  }

  /**
   * A failed site is named and excluded from the v2/v3 split.
   *
   * It must not be folded into v3 (a wrong "confirmed not v2") or v2, and it
   * must not silently vanish into a lower total.
   */
  public function testFailedSiteIsNamedAndExcluded(): void {
    $domains = ['a.example.edu', 'b.example.edu'];
    $results = [
      'a.example.edu' => $this->result(0, '1'),
      'b.example.edu' => $this->result(1, ''),
    ];

    $this->assertSame([
      'total' => 2,
      'reachable' => 1,
      'v2' => 1,
      'v3' => 0,
      'v2_sites' => ['a.example.edu'],
      'failed' => ['b.example.edu'],
    ], $this->command()->count($domains, $results));
  }

  /**
   * Every site failing reports zero reachable, not a false zero count.
   */
  public function testAllSitesFailedReportsZeroReachable(): void {
    $domains = ['a.example.edu', 'b.example.edu'];
    $results = [
      'a.example.edu' => $this->result(1, ''),
      'b.example.edu' => $this->result(255, ''),
    ];

    $tally = $this->command()->count($domains, $results);

    $this->assertSame(2, $tally['total']);
    $this->assertSame(0, $tally['reachable']);
    $this->assertSame([], $tally['v2_sites']);
    $this->assertSame(['a.example.edu', 'b.example.edu'], $tally['failed']);
  }

  /**
   * The remote script queries the sitenow_v2 split by name.
   */
  public function testScriptQueriesSitenowV2(): void {
    $this->assertStringContainsString(
      "Drupal::config('" . ReportSiteCountCommand::SPLIT_CONFIG_NAME . "')",
      $this->command()->script()
    );
    $this->assertStringContainsString("get('status')", $this->command()->script());
  }

}
