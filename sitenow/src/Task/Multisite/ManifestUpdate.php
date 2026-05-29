<?php

namespace SiteNow\Task\Multisite;

use Robo\Contract\RollbackInterface;
use Robo\Contract\SimulatedInterface;
use Robo\Result;
use Robo\Task\BaseTask;
use Symfony\Component\Yaml\Yaml;

/**
 * Adds (or removes) a site entry in blt/manifest.yml.
 */
class ManifestUpdate extends BaseTask implements SimulatedInterface, RollbackInterface {

  /**
   * The target application name.
   *
   * @var string
   */
  private string $app = '';

  /**
   * The multisite host to add.
   *
   * @var string
   */
  private string $host = '';

  /**
   * Constructs a ManifestUpdate task.
   *
   * @param string $manifestPath
   *   Absolute path to blt/manifest.yml.
   */
  public function __construct(private string $manifestPath) {}

  /**
   * Configures the task to add a host under an application.
   *
   * @return $this
   */
  public function add(string $app, string $host): static {
    $this->app = $app;
    $this->host = $host;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function run(): Result {
    $manifest = Yaml::parseFile($this->manifestPath) ?? [];
    if (!isset($manifest[$this->app])) {
      $manifest[$this->app] = [];
    }
    $manifest[$this->app][] = $this->host;
    $this->sortAndWrite($manifest);
    $this->printTaskInfo("Added <info>{$this->host}</info> to <info>{$this->app}</info> in manifest.");
    return Result::success($this);
  }

  /**
   * {@inheritdoc}
   */
  public function simulate($context): void {
    $this->printTaskInfo("Would add <info>{$this->host}</info> to <info>{$this->app}</info> in {$this->manifestPath}.");
  }

  /**
   * {@inheritdoc}
   */
  public function rollback(): void {
    if (!file_exists($this->manifestPath) || !$this->host) {
      return;
    }
    $manifest = Yaml::parseFile($this->manifestPath) ?? [];
    if (isset($manifest[$this->app])) {
      $key = array_search($this->host, $manifest[$this->app]);
      if ($key !== FALSE) {
        unset($manifest[$this->app][$key]);
        $manifest[$this->app] = array_values($manifest[$this->app]);
      }
      if (empty($manifest[$this->app])) {
        unset($manifest[$this->app]);
      }
    }
    $this->sortAndWrite($manifest);
    $this->printTaskInfo("Rolled back: removed {$this->host} from {$this->app} in manifest.");
  }

  /**
   * Sorts the manifest by application and host, then writes it.
   *
   * @param array $manifest
   *   The manifest data to write.
   */
  private function sortAndWrite(array $manifest): void {
    ksort($manifest);
    foreach ($manifest as &$app) {
      sort($app);
    }
    file_put_contents(
      $this->manifestPath,
      Yaml::dump($manifest, 8, 2, Yaml::DUMP_OBJECT_AS_MAP)
    );
  }

}
