<?php

namespace SiteNow\Report;

/**
 * Writes report rows to a timestamped CSV file at the repository root.
 */
class CsvWriter {

  /**
   * Timezone the filename timestamp is rendered in.
   *
   * Pinned so the same command stamps the same time wherever it runs: the
   * container's PHP is America/Chicago while the host CLI defaults to UTC,
   * which would file a late-afternoon export under tomorrow's date.
   */
  const TIMEZONE = 'America/Chicago';

  /**
   * Filename timestamp: sortable date, then hour and minute.
   *
   * Seconds are omitted, so two runs of one report with the same scope inside
   * a single minute collide and the later truncates the earlier. The fleet
   * commands take minutes to fan out, which keeps that off the realistic path.
   */
  const TIMESTAMP_FORMAT = 'Y-m-d_H\\hi';

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
   * @param array $scope
   *   Optional values describing what the report covers (e.g. the apps queried
   *   and the threshold applied), appended to the filename in the given order
   *   so two runs of one report are told apart by their arguments rather than
   *   by timestamp alone.
   */
  public function __construct(string $repo_root, string $filename_prefix, array $headers, array $scope = []) {
    $this->filepath = "{$repo_root}/{$filename_prefix}-" . $this->basename($scope) . '.csv';

    // Mode 'w' truncates any existing file. The handle stays open for the
    // writer's lifetime and is closed in the destructor.
    $this->handle = fopen($this->filepath, 'w');
    fputcsv($this->handle, $headers, ',', '"', '\\');
  }

  /**
   * Build the timestamp-and-scope portion of the filename.
   *
   * Underscores separate the segments because hyphens already appear inside
   * them ('2026-07-28', 'all-apps'), leaving an all-hyphen name with no
   * visible field boundaries.
   *
   * @param array $scope
   *   The caller's scope values, before sanitizing.
   *
   * @return string
   *   The timestamp followed by each non-empty scope value.
   */
  private function basename(array $scope): string {
    $stamp = (new \DateTime('now', new \DateTimeZone(self::TIMEZONE)))
      ->format(self::TIMESTAMP_FORMAT);

    $segments = array_filter(array_map([$this, 'slug'], $scope), fn($s) => $s !== '');

    return implode('_', array_merge([$stamp], $segments));
  }

  /**
   * Reduce one scope value to characters that are safe in a filename.
   *
   * Scope values reach here from command-line input, so the result is limited
   * to an allowlist rather than filtered for known-bad characters. Hyphens
   * survive ('all-apps'); spaces do not ('6 months' becomes '6months').
   *
   * @param string $value
   *   The raw scope value.
   *
   * @return string
   *   The sanitized value, which may be an empty string.
   */
  private function slug(string $value): string {
    return preg_replace('/[^a-z0-9+-]/', '', strtolower($value));
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
