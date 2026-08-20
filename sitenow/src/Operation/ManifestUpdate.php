<?php

namespace SiteNow\Operation;

use SiteNow\Config\Manifest;

/**
 * Adds a site entry under an application in blt/manifest.yml.
 */
class ManifestUpdate {

  public function __construct(
    private string $manifestPath,
    private string $app,
    private string $host,
  ) {}

  /**
   * Add the host under the application.
   */
  public function run(): void {
    (new Manifest($this->manifestPath))->addSite($this->app, $this->host);
  }

}
