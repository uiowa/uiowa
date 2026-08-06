<?php

namespace SiteNow\Operation;

use Symfony\Component\Yaml\Yaml;

/**
 * Removes a site entry from an application in blt/manifest.yml.
 *
 * The inverse of ManifestUpdate, and deliberately its mirror image: both sort
 * the whole file before writing, so an add and a remove produce the same
 * canonical ordering.
 */
class ManifestRemove {

  public function __construct(
    private string $manifestPath,
    private string $app,
    private string $host,
  ) {}

  /**
   * Remove the host from the application, then sort and write the manifest.
   *
   * An application left with no sites is dropped entirely, matching the shape
   * the manifest is expected to hold: an application key always has at least
   * one site under it.
   */
  public function run(): void {
    $manifest = Yaml::parseFile($this->manifestPath) ?? [];

    // Idempotent: re-running after a partial run, or against a site already
    // removed by hand, is a no-op rather than an error.
    if (isset($manifest[$this->app])) {
      $manifest[$this->app] = array_values(array_filter(
        $manifest[$this->app],
        fn($site) => $site !== $this->host
      ));

      if (empty($manifest[$this->app])) {
        unset($manifest[$this->app]);
      }
    }

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
