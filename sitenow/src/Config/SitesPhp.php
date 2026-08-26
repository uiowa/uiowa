<?php

namespace SiteNow\Config;

/**
 * Reader and writer for the site directory aliases in docroot/sites/sites.php.
 *
 * The file maps request hosts to the directory under docroot/sites that serves
 * them, so one site answers at its canonical domain and at its ddev and
 * internal Acquia names.
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
   * Idempotent.
   *
   * @param string $directory
   *   The site directory the aliases point at.
   * @param string[] $hosts
   *   The request hosts to alias to that directory.
   *
   * @throws \RuntimeException
   *   If the file cannot be read or written.
   */
  public function addAliases(string $directory, array $hosts): void {
    $contents = file_get_contents($this->path);

    if ($contents === FALSE) {
      throw new \RuntimeException("Failed to read {$this->path}.");
    }

    if (str_contains($contents, $this->marker($directory))) {
      return;
    }

    // The leading empty element separates this block from what precedes it.
    $lines = [''];
    $lines[] = $this->marker($directory);

    foreach ($hosts as $host) {
      $lines[] = "\$sites['{$host}'] = '{$directory}';";
    }

    if (file_put_contents($this->path, implode("\n", $lines) . "\n", FILE_APPEND) === FALSE) {
      throw new \RuntimeException("Failed to write to {$this->path}.");
    }
  }

  /**
   * Strip a site's alias lines and its marker comment.
   *
   * Removes every alias line pointing at the directory, whichever hosts they
   * name.
   *
   * Idempotent.
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

    // Alias lines and the marker both name the site directory.
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

    // preg_replace returns NULL on failure, which the write below would take
    // for an empty string and truncate the file.
    $body = implode("\n", $kept);
    $result = preg_replace("/\n{3,}/", "\n\n", $body) ?? $body;

    if (file_put_contents($this->path, $result) === FALSE) {
      throw new \RuntimeException("Failed to write to {$this->path}.");
    }
  }

  /**
   * The comment that heads a site's alias block.
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
