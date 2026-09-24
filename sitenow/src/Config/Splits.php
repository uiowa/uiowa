<?php

namespace SiteNow\Config;

use Symfony\Component\Yaml\Yaml;

/**
 * The config splits defined in the repository.
 */
class Splits {

  /**
   * Splits that must be active before the keyed split can be activated.
   */
  const DEPENDENCIES = [
    'p2lb' => ['sitenow_v2'],
  ];

  /**
   * Constructs the split list.
   *
   * @param string $repoRoot
   *   Absolute path to the repository root.
   */
  public function __construct(
    private string $repoRoot,
  ) {}

  /**
   * Get the feature splits.
   *
   * These are defined in config/default and export under config/features.
   *
   * @return array<string, string>
   *   Repository-relative export folders keyed by split ID, sorted by ID.
   */
  public function features(): array {
    $features = [];
    foreach (glob("{$this->repoRoot}/config/default/config_split.config_split.*.yml") as $file) {
      $split = Yaml::parseFile($file);
      $folder = $this->relativeFolder($split['folder'] ?? '');
      if (isset($split['id']) && str_starts_with($folder, 'config/features/')) {
        $features[$split['id']] = $folder;
      }
    }
    ksort($features);
    return $features;
  }

  /**
   * Get the site splits.
   *
   * Each host has one, defined as
   * config/sites/<host>/config_split.config_split.site.yml.
   *
   * @return array<string, string>
   *   Repository-relative export folders keyed by host, sorted by host.
   */
  public function sites(): array {
    $sites = [];
    foreach (glob("{$this->repoRoot}/config/sites/*/config_split.config_split.site.yml") as $file) {
      $split = Yaml::parseFile($file);
      $sites[basename(dirname($file))] = $this->relativeFolder($split['folder'] ?? '');
    }
    ksort($sites);
    return $sites;
  }

  /**
   * Get the splits to activate for a feature split, dependencies first.
   *
   * @param string $id
   *   The feature split ID.
   *
   * @return string[]
   *   Split IDs in activation order, ending with $id.
   */
  public function activationOrder(string $id): array {
    return [...(self::DEPENDENCIES[$id] ?? []), $id];
  }

  /**
   * Convert a split's folder setting to a repository-relative path.
   *
   * @param string $folder
   *   The folder as stored in the split, relative to the docroot.
   *
   * @return string
   *   The folder relative to the repository root, e.g. config/features/event.
   */
  private function relativeFolder(string $folder): string {
    return preg_replace('#^\.\./#', '', rtrim($folder, '/'));
  }

}
