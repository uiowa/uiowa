<?php

namespace Uiowa\Tests\PHPUnit\Unit;

use Drupal\Tests\UnitTestCase;
use SiteNow\Command\ReportSplitsCommand;

/**
 * Unit tests for the splits report's drush-output parsing.
 *
 * The parseSplitStatuses() method turns a site's drush php:eval output into a
 * map of split_id => active. Pure logic: no drush or SSH. Describing a failed
 * site is the shared DescribesDrushFailures trait's job, covered by
 * ReportUsersTest.
 *
 * @group unit
 */
class ReportSplitsTest extends UnitTestCase {

  /**
   * A subclass exposing the protected parser and the remote script.
   */
  private function command(): ReportSplitsCommand {
    return new class extends ReportSplitsCommand {

      /**
       * {@inheritdoc}
       */
      public function parse(string $output): array {
        return $this->parseSplitStatuses($output);
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
   * Clean output parses to a split_id => active map.
   */
  public function testParsesCleanOutput(): void {
    $this->assertSame([
      'event' => TRUE,
      'thesis_defense' => FALSE,
      'prod' => TRUE,
    ], $this->command()->parse("event:1\nthesis_defense:0\nprod:1\n"));
  }

  /**
   * Chatter lines without a 0/1 status value are skipped.
   */
  public function testSkipsChatter(): void {
    $output = implode("\n", [
      ' [notice] Command output:',
      'Connecting to prod...',
      'event:1',
      'noise-without-colon',
      'malformed:value',
      'thesis_defense:0',
    ]);

    $this->assertSame(
      ['event' => TRUE, 'thesis_defense' => FALSE],
      $this->command()->parse($output)
    );
  }

  /**
   * Output with no parseable status lines parses to nothing.
   *
   * The caller reads an empty map as a failed site: every site carries the
   * environmental splits, so a site with no statuses did not answer.
   */
  public function testEmptyOutputParsesToNothing(): void {
    $this->assertSame([], $this->command()->parse("just chatter\nno statuses here\n"));
    $this->assertSame([], $this->command()->parse(''));
  }

  /**
   * The remote script strips exactly the config prefix it lists by.
   *
   * The prefix and the substr() offset are built from one constant; this fails
   * if they ever drift apart and split ids come back mangled.
   */
  public function testScriptOffsetMatchesPrefix(): void {
    $prefix = ReportSplitsCommand::SPLIT_CONFIG_PREFIX;

    $this->assertStringContainsString("listAll(\"{$prefix}\")", $this->command()->script());
    $this->assertStringContainsString('substr($n, ' . strlen($prefix) . ')', $this->command()->script());
  }

}
