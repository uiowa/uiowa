<?php

namespace SiteNow\Operation;

/**
 * Removes a site's directory aliases from docroot/sites/sites.php.
 *
 * Removes the marker comment and every alias line pointing at the site
 * directory, whichever domains they name, so aliases added by hand and blocks
 * whose formatting has drifted are both cleaned up.
 */
class SitesPhpRemove {

  public function __construct(
    private string $filePath,
    private string $directory,
  ) {}

  /**
   * Strip the site's alias lines and its marker comment.
   *
   * Idempotent: a file with no aliases for the site is left unchanged rather
   * than raising, so a retry after a partial run is safe.
   *
   * @throws \RuntimeException
   *   If the file cannot be read or written.
   */
  public function run(): void {
    $contents = file_get_contents($this->filePath);
    if ($contents === FALSE) {
      throw new \RuntimeException("Failed to read {$this->filePath}.");
    }

    // Every alias line assigns the site *directory*, so that is what is
    // matched; the marker comment names it too, since a site's directory and
    // its host agree at creation.
    $marker = "// Directory aliases for {$this->directory}.";
    $alias = '/^\$sites\[[^\]]+\]\s*=\s*' . preg_quote("'{$this->directory}'", '/') . '\s*;$/';

    $kept = [];
    foreach (explode("\n", $contents) as $line) {
      $trimmed = trim($line);
      if ($trimmed === $marker || preg_match($alias, $trimmed)) {
        continue;
      }
      $kept[] = $line;
    }

    // Removing a block leaves the blank lines that surrounded it adjacent;
    // collapse them so repeated deletions do not stretch the file.
    $result = preg_replace("/\n{3,}/", "\n\n", implode("\n", $kept));

    if (file_put_contents($this->filePath, $result) === FALSE) {
      throw new \RuntimeException("Failed to write to {$this->filePath}.");
    }
  }

}
