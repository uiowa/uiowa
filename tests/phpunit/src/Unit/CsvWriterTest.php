<?php

namespace Uiowa\Tests\PHPUnit\Unit;

use Drupal\Tests\UnitTestCase;
use SiteNow\Report\CsvWriter;

/**
 * Unit tests for the report CSV export writer.
 *
 * Covers the shared export sink used by every report command's --export
 * option: header on construction, appended rows, and the resolved path.
 *
 * @group unit
 */
class CsvWriterTest extends UnitTestCase {

  /**
   * Writes a header on construction and appends rows in order.
   */
  public function testWritesHeaderAndRows(): void {
    $dir = sys_get_temp_dir() . '/sn-csv-' . uniqid();
    mkdir($dir);

    try {
      $writer = new CsvWriter($dir, 'Test-Report', ['Application', 'URL']);
      $writer->writeRow(['uiowa02', 'vote.uiowa.edu']);
      $writer->writeRow(['uiowa09', 'cif.uiowa.edu']);

      $path = $writer->getPath();
      $this->assertFileExists($path);
      $this->assertStringStartsWith("{$dir}/Test-Report-", $path);
      $this->assertStringEndsWith('.csv', $path);

      $lines = array_values(array_filter(explode("\n", file_get_contents($path)), fn($l) => $l !== ''));
      $this->assertSame([
        'Application,URL',
        'uiowa02,vote.uiowa.edu',
        'uiowa09,cif.uiowa.edu',
      ], $lines);
    }
    finally {
      if (isset($path) && file_exists($path)) {
        unlink($path);
      }
      rmdir($dir);
    }
  }

  /**
   * Scope values follow the timestamp, separated by underscores.
   */
  public function testFilenameCarriesScope(): void {
    $dir = sys_get_temp_dir() . '/sn-csv-' . uniqid();
    mkdir($dir);

    try {
      $writer = new CsvWriter($dir, 'Test-Report', ['Email'], ['uiowa02+uiowa03', '6 months']);
      $path = $writer->getPath();

      // Spaces are stripped from '6 months'; the '+' joining apps survives.
      $this->assertMatchesRegularExpression(
        '#/Test-Report-\d{4}-\d{2}-\d{2}_\d{2}h\d{2}_uiowa02\+uiowa03_6months\.csv$#',
        $path,
      );
    }
    finally {
      if (isset($path) && file_exists($path)) {
        unlink($path);
      }
      rmdir($dir);
    }
  }

  /**
   * Without scope the filename stops after the timestamp.
   *
   * The report commands that pass no scope keep a name the repository's
   * '/SiteNow-*-Report-*.csv' ignore rule still matches.
   */
  public function testFilenameOmitsEmptyScope(): void {
    $dir = sys_get_temp_dir() . '/sn-csv-' . uniqid();
    mkdir($dir);

    try {
      $writer = new CsvWriter($dir, 'Test-Report', ['Email'], ['', '  ']);
      $path = $writer->getPath();

      $this->assertMatchesRegularExpression(
        '#/Test-Report-\d{4}-\d{2}-\d{2}_\d{2}h\d{2}\.csv$#',
        $path,
      );
    }
    finally {
      if (isset($path) && file_exists($path)) {
        unlink($path);
      }
      rmdir($dir);
    }
  }

  /**
   * Values containing commas are quoted so columns stay aligned.
   */
  public function testQuotesValuesWithCommas(): void {
    $dir = sys_get_temp_dir() . '/sn-csv-' . uniqid();
    mkdir($dir);

    try {
      $writer = new CsvWriter($dir, 'Test-Report', ['Split', 'Note']);
      $writer->writeRow(['event', 'a, b, c']);
      $path = $writer->getPath();

      $rows = array_map('str_getcsv', array_values(array_filter(explode("\n", file_get_contents($path)), fn($l) => $l !== '')));
      $this->assertSame(['event', 'a, b, c'], $rows[1]);
    }
    finally {
      if (isset($path) && file_exists($path)) {
        unlink($path);
      }
      rmdir($dir);
    }
  }

}
