<?php

namespace SiteNow\Config;

/**
 * Reader and writer for the site directory aliases in docroot/sites/sites.php.
 *
 * The file maps request hosts to the directory under docroot/sites that serves
 * them, which is how one site is reached at its canonical domain and at its
 * ddev and internal Acquia names.
 */
class SitesPhp {

  /**
   * Constructs a sites.php reader/writer.
   *
   * @param string $path
   *   Absolute path to the sites.php file.
   */
  public function __construct(
    private string $path,
  ) {}

  /**
   * Append the directory aliases for a host.
   *
   * Idempotent: a retry after a partial run must not duplicate the aliases.
   *
   * @param string $directory
   *   The site directory the aliases point at.
   * @param string[] $hosts
   *   The request hosts to alias to that directory.
   *
   * @throws \RuntimeException
   *   If the file cannot be written.
   */
  public function addAliases(string $directory, array $hosts): void {
    if (str_contains((string) file_get_contents($this->path), $this->marker($directory))) {
      return;
    }

    $lines = [''];
    $lines[] = $this->marker($directory);

    foreach ($hosts as $host) {
      $lines[] = "\$sites['{$host}'] = '{$directory}';";
    }

    $lines[] = '';

    if (file_put_contents($this->path, implode("\n", $lines) . "\n", FILE_APPEND) === FALSE) {
      throw new \RuntimeException("Failed to write to {$this->path}.");
    }
  }

  /**
   * Strip a site's alias lines and its marker comment.
   *
   * Removes every alias line pointing at the directory, whichever hosts they
   * name, so aliases added by hand and blocks whose formatting has drifted are
   * both cleaned up.
   *
   * Idempotent: a file with no aliases for the site is left unchanged rather
   * than raising, so a retry after a partial run is safe.
   *
   * @param string $directory
   *   The site directory whose aliases go.
   *
   * @throws \RuntimeException
   *   If the file cannot be read or written.
   */
  public function removeAliases(string $directory): void {
    $contents = file_get_contents($this->path);

    if ($contents === FALSE) {
      throw new \RuntimeException("Failed to read {$this->path}.");
    }

    // Every alias line assigns the site *directory*, so that is what is
    // matched; the marker comment names it too, since a site's directory and
    // its host agree at creation.
    $marker = $this->marker($directory);
    $alias = '/^\$sites\[[^\]]+\]\s*=\s*' . preg_quote("'{$directory}'", '/') . '\s*;$/';

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

    if (file_put_contents($this->path, $result) === FALSE) {
      throw new \RuntimeException("Failed to write to {$this->path}.");
    }
  }

  /**
   * The comment that heads a site's alias block.
   *
   * Written by addAliases() and matched by removeAliases(), so both derive it
   * from one place.
   *
   * @param string $directory
   *   The site directory.
   *
   * @return string
   *   The marker comment line.
   */
  private function marker(string $directory): string {
    return "// Directory aliases for {$directory}.";
  }

}
