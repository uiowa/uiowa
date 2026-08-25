<?php

namespace SiteNow\Config;

use Symfony\Component\Yaml\Yaml;

/**
 * Reader and writer for the site manifest (blt/manifest.yml).
 *
 * The manifest is keyed by Acquia application (AH_SITE_GROUP), each holding
 * the list of multisite hosts that application owns. It is the source of truth
 * for which sites exist and where they are deployed.
 */
class Manifest {

  /**
   * Constructs a manifest reader/writer.
   *
   * @param string $path
   *   Absolute path to the manifest YAML file.
   */
  public function __construct(
    private string $path,
  ) {}

  /**
   * The whole manifest, keyed by application.
   *
   * @return array
   *   Site hosts keyed by application name; empty when the file is absent or
   *   empty.
   */
  public function all(): array {
    return is_file($this->path) ? (Yaml::parseFile($this->path) ?: []) : [];
  }

  /**
   * The sites one application owns.
   *
   * @param string $app
   *   The application name.
   *
   * @return string[]
   *   Site hosts, empty when the application is not in the manifest.
   */
  public function sites(string $app): array {
    return $this->all()[$app] ?? [];
  }

  /**
   * Add a host under an application.
   *
   * Idempotent: a retry after a partial run must not duplicate the entry.
   *
   * @param string $app
   *   The application name.
   * @param string $host
   *   The site host to add.
   */
  public function addSite(string $app, string $host): void {
    $manifest = $this->all();

    if (!isset($manifest[$app])) {
      $manifest[$app] = [];
    }

    if (!in_array($host, $manifest[$app], TRUE)) {
      $manifest[$app][] = $host;
    }

    $this->save($manifest);
  }

  /**
   * Remove a host from an application.
   *
   * An application left with no sites is dropped entirely, matching the shape
   * the manifest holds: an application key always has at least one site under
   * it.
   *
   * Idempotent: removing a host that is already gone is a no-op, so a retry
   * after a partial run is safe.
   *
   * @param string $app
   *   The application name.
   * @param string $host
   *   The site host to remove.
   */
  public function removeSite(string $app, string $host): void {
    $manifest = $this->all();

    if (isset($manifest[$app])) {
      $manifest[$app] = array_values(array_filter(
        $manifest[$app],
        fn($site) => $site !== $host
      ));

      if (empty($manifest[$app])) {
        unset($manifest[$app]);
      }
    }

    $this->save($manifest);
  }

  /**
   * Sort and write the manifest.
   *
   * Applications sorted, hosts sorted within each application, one YAML dump
   * style. The single place the file's layout is decided, so the file reads the
   * same no matter which command last wrote it.
   *
   * @param array $manifest
   *   The manifest to write, keyed by application.
   *
   * @throws \RuntimeException
   *   If the file cannot be written.
   */
  private function save(array $manifest): void {
    ksort($manifest);

    foreach ($manifest as &$sites) {
      sort($sites);
    }

    $written = file_put_contents(
      $this->path,
      Yaml::dump($manifest, 8, 2, Yaml::DUMP_OBJECT_AS_MAP)
    );

    if ($written === FALSE) {
      throw new \RuntimeException("Failed to write to {$this->path}.");
    }
  }

}
