<?php

namespace SiteNow\Task\Multisite;

/**
 * Task factory methods for the multisite-state tasks.
 *
 * Tasks are built through the collection builder (not `new`) so Robo wraps
 * them under --simulate; they never run for real in simulated mode.
 */
trait Tasks {

  /**
   * Creates a SitesPhpUpdate task.
   *
   * @return \SiteNow\Task\Multisite\SitesPhpUpdate
   *   The task, proxied through a collection builder.
   */
  protected function taskSitesPhpUpdate(string $filePath) {
    return $this->task(SitesPhpUpdate::class, $filePath);
  }

  /**
   * Creates a ManifestUpdate task.
   *
   * @return \SiteNow\Task\Multisite\ManifestUpdate
   *   The task, proxied through a collection builder.
   */
  protected function taskManifestUpdate(string $manifestPath) {
    return $this->task(ManifestUpdate::class, $manifestPath);
  }

}
