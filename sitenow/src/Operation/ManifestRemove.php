<?php

namespace SiteNow\Operation;

use SiteNow\Config\Manifest;

/**
 * Removes a site entry from an application in blt/manifest.yml.
 *
 * A step-sized wrapper around Manifest::removeSite(), so a plan carries one
 * operation per side effect and its label can name that effect literally.
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
