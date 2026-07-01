<?php

namespace SiteNow\Config;

use Symfony\Component\Yaml\Yaml;

/**
 * Reader for the SiteNow application registry (sitenow/applications.yml).
 *
 * The registry is the curated list of Acquia Cloud applications that are valid
 * SiteNow multisite targets. It carries each application's UUID and an optional
 * reserved flag that excludes it from automatic selection.
 */
class Applications {

  /**
   * Registry entries keyed by application name.
   *
   * @var array
   */
  private array $applications;

  /**
   * Constructs an Applications registry reader.
   *
   * @param string $path
   *   Absolute path to the registry YAML file.
   */
  public function __construct(string $path) {
    $data = Yaml::parseFile($path) ?? [];
    $this->applications = $data['applications'] ?? [];
  }

  /**
   * All registry entries, keyed by application name.
   *
   * @return array
   *   Each entry: ['uuid' => string, 'reserved' => bool (optional)].
   */
  public function all(): array {
    return $this->applications;
  }

  /**
   * Application names in registry order.
   *
   * @return string[]
   *   The application names.
   */
  public function names(): array {
    return array_keys($this->applications);
  }

  /**
   * The UUID for a named application.
   *
   * @param string $name
   *   The application name.
   *
   * @return string|null
   *   The UUID, or NULL if the application is not registered.
   */
  public function uuid(string $name): ?string {
    return $this->applications[$name]['uuid'] ?? NULL;
  }

  /**
   * The git remote URL for a named application.
   *
   * @param string $name
   *   The application name.
   *
   * @return string|null
   *   The git remote URL, or NULL if the application is not registered or has
   *   no remote configured.
   */
  public function remote(string $name): ?string {
    return $this->applications[$name]['remote'] ?? NULL;
  }

  /**
   * Whether an application is reserved (excluded from automatic selection).
   *
   * @param string $name
   *   The application name.
   *
   * @return bool
   *   TRUE if reserved.
   */
  public function isReserved(string $name): bool {
    return (bool) ($this->applications[$name]['reserved'] ?? FALSE);
  }

  /**
   * Entries eligible for automatic selection, keyed by name.
   *
   * @return array
   *   The non-reserved registry entries.
   */
  public function autoSelectable(): array {
    return array_filter($this->applications, fn($entry) => empty($entry['reserved']));
  }

}
