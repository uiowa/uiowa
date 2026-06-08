<?php

namespace SiteNow\Operation;

use Symfony\Component\Yaml\Yaml;

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
   * Add the host under the application, then sort and write the manifest.
   */
  public function run(): void {
    $manifest = Yaml::parseFile($this->manifestPath) ?? [];
    if (!isset($manifest[$this->app])) {
      $manifest[$this->app] = [];
    }
    $manifest[$this->app][] = $this->host;

    ksort($manifest);
    foreach ($manifest as &$sites) {
      sort($sites);
    }

    file_put_contents(
      $this->manifestPath,
      Yaml::dump($manifest, 8, 2, Yaml::DUMP_OBJECT_AS_MAP)
    );
  }

}
