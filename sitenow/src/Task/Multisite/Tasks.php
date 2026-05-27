<?php

namespace SiteNow\Task\Multisite;

trait Tasks {

  protected function taskSitesPhpUpdate(string $filePath): SitesPhpUpdate {
    return new SitesPhpUpdate($filePath);
  }

  protected function taskManifestUpdate(string $manifestPath): ManifestUpdate {
    return new ManifestUpdate($manifestPath);
  }

}
