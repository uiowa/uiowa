<?php

namespace SiteNow\Operation;

use SiteNow\Config\Manifest;

/**
 * Removes a site entry from an application in blt/manifest.yml.
 */
class ManifestRemove {

  public function __construct(
    private string $manifestPath,
    private string $app,
    private string $host,
  ) {}

  /**
   * Remove the host from the application.
   */
  public function run(): void {
    (new Manifest($this->manifestPath))->removeSite($this->app, $this->host);
  }

}
