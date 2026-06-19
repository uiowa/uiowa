<?php

namespace Uiowa\Tests\PHPUnit\Unit;

use Drupal\Tests\UnitTestCase;
use SiteNow\Command\ReportSplitsCommand;

/**
 * Unit tests for the splits report's drush-output parsing.
 *
 * The parseSplitStatuses() method turns a site's drush php:eval output into a
 * map of split_id => active, or FALSE with a reason. Pure logic: no drush or
 * SSH.
 *
 * @group unit
 */
class ReportSplitsTest extends UnitTestCase {

  /**
   * Expose the protected parser and capture its by-ref error out-param.
   *
   * @return array{0: array<string,bool>|false, 1: string|null}
   *   The parser result and the error message it set.
   */
  private function parse(string $output, int $exit, string $stderr = ''): array {
    $command = new class extends ReportSplitsCommand {

      /**
       * Calls the protected parser, returning [result, error].
       */
      public function expose(string $output, int $exit, string $stderr): array {
        $error = NULL;
        $result = $this->parseSplitStatuses($output, $exit, $error, $stderr);
        return [$result, $error];
      }

    };
    return $command->expose($output, $exit, $stderr);
  }

  /**
   * Clean output parses to a split_id => active map.
   */
  public function testParsesCleanOutput(): void {
    [$result, $error] = $this->parse("event:1\nthesis_defense:0\nprod:1\n", 0);
    $this->assertSame([
      'event' => TRUE,
      'thesis_defense' => FALSE,
      'prod' => TRUE,
    ], $result);
    $this->assertNull($error);
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
    [$result, $error] = $this->parse($output, 0);
    $this->assertSame(['event' => TRUE, 'thesis_defense' => FALSE], $result);
    $this->assertNull($error);
  }

  /**
   * Output with no parseable status lines is a failure.
   */
  public function testEmptyOutputFails(): void {
    [$result, $error] = $this->parse("just chatter\nno statuses here\n", 0);
    $this->assertFalse($result);
    $this->assertSame('no parseable split status lines in drush output', $error);
  }

  /**
   * A non-zero exit reports the last stderr line as the reason.
   */
  public function testNonZeroExitUsesStderrTail(): void {
    [$result, $error] = $this->parse('', 1, "warming up\nalias siteid.prod not found");
    $this->assertFalse($result);
    $this->assertSame('drush exit 1 (alias siteid.prod not found)', $error);
  }

  /**
   * A non-zero exit with empty stderr falls back to the stdout tail.
   */
  public function testNonZeroExitFallsBackToStdoutTail(): void {
    [$result, $error] = $this->parse("partial output\nfatal: boom", 2, '');
    $this->assertFalse($result);
    $this->assertSame('drush exit 2 (fatal: boom)', $error);
  }

  /**
   * A non-zero exit with no output at all still reports the exit code.
   */
  public function testNonZeroExitNoOutput(): void {
    [$result, $error] = $this->parse('', 255, '');
    $this->assertFalse($result);
    $this->assertSame('drush exit 255', $error);
  }

}
