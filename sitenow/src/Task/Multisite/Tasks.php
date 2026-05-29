<?php

namespace SiteNow\Task\Multisite;

/**
 * Task factory methods for the multisite-state tasks.
 */
trait Tasks {

  /**
   * Creates a SitesPhpUpdate task.
   */
  protected function taskSitesPhpUpdate(string $filePath): SitesPhpUpdate {
    return new SitesPhpUpdate($filePath);
  }

  /**
   * Creates a ManifestUpdate task.
   */
  protected function taskManifestUpdate(string $manifestPath): ManifestUpdate {
    return new ManifestUpdate($manifestPath);
  }

}
