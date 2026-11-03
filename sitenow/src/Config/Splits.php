<?php

namespace SiteNow\Config;

use Symfony\Component\Yaml\Yaml;

/**
 * The config splits defined in the repository.
 */
class Splits {

  /**
   * The config name prefix shared by every split definition.
   */
  const PREFIX = 'config_split.config_split.';

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
    foreach (glob("{$this->repoRoot}/config/default/" . self::PREFIX . '*.yml') as $file) {
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
   * A split depends on the splits its definition lists under
   * dependencies.enforced.config.
   *
   * @param string $id
   *   The feature split ID.
   *
   * @return string[]
   *   Split IDs in activation order, ending with $id.
   */
  public function activationOrder(string $id): array {
    $order = [];
    $this->addWithDependencies($id, $order, []);
    return $order;
  }

  /**
   * Append a split to an activation order after its dependencies.
   *
   * @param string $id
   *   The split ID.
   * @param string[] $order
   *   The order being built.
   * @param string[] $path
   *   The splits whose dependencies are being resolved, to stop a cycle.
   */
  private function addWithDependencies(string $id, array &$order, array $path): void {
    if (in_array($id, $order, TRUE) || in_array($id, $path, TRUE)) {
      return;
    }
    $file = "{$this->repoRoot}/config/default/" . self::PREFIX . "{$id}.yml";
    $split = is_file($file) ? Yaml::parseFile($file) : [];
    foreach ($split['dependencies']['enforced']['config'] ?? [] as $name) {
      if (str_starts_with($name, self::PREFIX)) {
        $this->addWithDependencies(substr($name, strlen(self::PREFIX)), $order, [...$path, $id]);
      }
    }
    $order[] = $id;
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
