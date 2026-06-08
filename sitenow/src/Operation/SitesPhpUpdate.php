<?php

namespace SiteNow\Operation;

/**
 * Appends site directory aliases to docroot/sites/sites.php.
 */
class SitesPhpUpdate {

  public function __construct(
    private string $filePath,
    private string $host,
    private string $local,
    private string $dev,
    private string $test,
    private string $prod,
  ) {}

  /**
   * Append the directory aliases for the host.
   *
   * @throws \RuntimeException
   *   If the file cannot be written.
   */
  public function run(): void {
    $block = <<<EOD

// Directory aliases for {$this->host}.
\$sites['{$this->local}'] = '{$this->host}';
\$sites['{$this->dev}'] = '{$this->host}';
\$sites['{$this->test}'] = '{$this->host}';
\$sites['{$this->prod}'] = '{$this->host}';

EOD;

    if (file_put_contents($this->filePath, $block, FILE_APPEND) === FALSE) {
      throw new \RuntimeException("Failed to write to {$this->filePath}.");
    }
  }

}
