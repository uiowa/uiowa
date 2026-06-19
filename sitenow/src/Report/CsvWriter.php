<?php

namespace SiteNow\Report;

/**
 * Writes report rows to a timestamped CSV file at the repository root.
 */
class CsvWriter {

  /**
   * Absolute path to the export file.
   */
  private string $filepath;

  /**
   * The open file handle, held for the writer's lifetime.
   *
   * @var resource
   */
  private $handle;

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

    // Mode 'w' truncates any existing file. The handle stays open for the
    // writer's lifetime and is closed in the destructor.
    $this->handle = fopen($this->filepath, 'w');
    fputcsv($this->handle, $headers, ',', '"', '\\');
  }

  /**
   * Append one row to the export file.
   *
   * @param array $row
   *   The row values, in header order.
   */
  public function writeRow(array $row): void {
    fputcsv($this->handle, $row, ',', '"', '\\');
    // Flush each row so a long run's output is on disk as it goes.
    fflush($this->handle);
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

  /**
   * Closes the file handle.
   */
  public function __destruct() {
    if (is_resource($this->handle)) {
      fclose($this->handle);
    }
  }

}
