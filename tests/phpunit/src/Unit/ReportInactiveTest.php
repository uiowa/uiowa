<?php

namespace Uiowa\Tests\PHPUnit\Unit;

use Drupal\Tests\UnitTestCase;
use SiteNow\Command\ReportInactiveCommand;

/**
 * Unit tests for the inactive report's drush-output parsing.
 *
 * The parseLastLogin() and parseLastRevision() methods turn a site's drush
 * output into a timestamp, NULL (no data), or FALSE (query error). Pure logic:
 * no drush or SSH.
 *
 * @group unit
 */
class ReportInactiveTest extends UnitTestCase {

  /**
   * A subclass exposing the protected parsers.
   */
  private function command(): ReportInactiveCommand {
    return new class extends ReportInactiveCommand {

      /**
       * Exposes parseLastLogin().
       */
      public function login(string $output, int $exit): int|null|false {
        return $this->parseLastLogin($output, $exit);
      }

      /**
       * Exposes parseLastRevision().
       */
      public function revision(string $output, int $exit): int|null|false {
        return $this->parseLastRevision($output, $exit);
      }

    };
  }

  /**
   * A non-zero exit is a query error for both parsers.
   */
  public function testNonZeroExitIsError(): void {
    $command = $this->command();
    $this->assertFalse($command->login('{"2":{"uid":2,"login":"2025-01-01 00:00:00"}}', 1));
    $this->assertFalse($command->revision('1700000000', 1));
  }

  /**
   * The latest non-admin login wins; uid 1 and pre-2000 noise are skipped.
   */
  public function testParsesLatestLogin(): void {
    $json = json_encode([
      '1' => ['uid' => 1, 'login' => '2026-01-01 00:00:00'],
      '2' => ['uid' => 2, 'login' => '2024-05-01 12:00:00'],
      '3' => ['uid' => 3, 'login' => '2025-09-15 08:30:00'],
      '4' => ['uid' => 4, 'login' => '1969-12-31 00:00:00'],
    ]);
    $expected = strtotime('2025-09-15 08:30:00');
    $this->assertSame($expected, $this->command()->login($json, 0));
  }

  /**
   * Leading connection chatter before the JSON is stripped.
   */
  public function testStripsChatterBeforeJson(): void {
    $output = "Connecting to prod...\n" . json_encode(['2' => ['uid' => 2, 'login' => '2025-03-03 03:03:03']]);
    $this->assertSame(strtotime('2025-03-03 03:03:03'), $this->command()->login($output, 0));
  }

  /**
   * No users (empty JSON object) means no login data: NULL.
   */
  public function testEmptyUserListIsNull(): void {
    $this->assertNull($this->command()->login('{}', 0));
  }

  /**
   * Users with no usable login timestamps return NULL.
   */
  public function testNoUsableLoginIsNull(): void {
    $json = json_encode([
      '1' => ['uid' => 1, 'login' => '2026-01-01 00:00:00'],
      '2' => ['uid' => 2, 'login' => ''],
    ]);
    $this->assertNull($this->command()->login($json, 0));
  }

  /**
   * Malformed JSON and empty output are query errors: FALSE.
   */
  public function testMalformedLoginIsError(): void {
    $command = $this->command();
    $this->assertFalse($command->login('not json at all', 0));
    $this->assertFalse($command->login('', 0));
  }

  /**
   * A positive revision timestamp is parsed from numeric output.
   */
  public function testParsesRevisionTimestamp(): void {
    $this->assertSame(1700000000, $this->command()->revision(" 1700000000 \n", 0));
  }

  /**
   * A zero or empty MAX() result means no revisions: NULL.
   */
  public function testZeroRevisionIsNull(): void {
    $this->assertNull($this->command()->revision('0', 0));
  }

  /**
   * Non-numeric output (no rows) is a query error: FALSE.
   */
  public function testNonNumericRevisionIsError(): void {
    $this->assertFalse($this->command()->revision("no results\n", 0));
  }

}
