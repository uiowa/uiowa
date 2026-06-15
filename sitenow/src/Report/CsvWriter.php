<?php

namespace SiteNow\Report;

/**
 * Appends rows to a timestamped CSV export file at the repository root.
 *
 * Replaces SiteNowCommandsTrait::initializeCsvExport(), which depended on
 * Robo's say() for its announcement and resolved the repository root from
 * config that is rarely set.
 */
class CsvWriter {

  /**
   * Absolute path to the export file.
   */
  private string $filepath;

  /**
   * Creates the export file and writes the header row.
   *
   * @param string $repo_root
   *   Absolute path to the repository root.
   * @param string $filename_prefix
   *   Prefix for the CSV filename (e.g. 'SiteNow-Domains-Report').
   * @param array $headers
   *   Header column names.
   */
  public function __construct(string $repo_root, string $filename_prefix, array $headers) {
    $now = date('Ymd-His');
    $this->filepath = "{$repo_root}/{$filename_prefix}-{$now}.csv";

    if (file_exists($this->filepath)) {
      unlink($this->filepath);
    }

    $fp = fopen($this->filepath, 'w+');
    fputcsv($fp, $headers, ',', '"', '\\');
    fclose($fp);
  }

  /**
   * Append one row to the export file.
   *
   * @param array $row
   *   The row values, in header order.
   */
  public function writeRow(array $row): void {
    $fp = fopen($this->filepath, 'a');
    fputcsv($fp, $row, ',', '"', '\\');
    fclose($fp);
  }

  /**
   * Get the absolute path to the export file.
   *
   * @return string
   *   The filepath.
   */
  public function getPath(): string {
    return $this->filepath;
  }

}
