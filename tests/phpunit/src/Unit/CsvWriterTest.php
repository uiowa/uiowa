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
