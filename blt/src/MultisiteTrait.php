<?php

namespace Uiowa;

use Acquia\Blt\Robo\Common\YamlMunge;
use Acquia\Blt\Robo\Tasks\LoadTasks;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

/**
 * Shared utility methods for BLT extensions.
 */
trait MultisiteTrait {

  use LoadTasks;

  /**
   * Get the application from the prod remote Drush alias.
   *
   * @param string $id
   *   The multisite identifier.
   * @param string $env
   *   The environment to use for the Drush alias. Defaults to prod.
   *
   * @return string
   *   The application name.
   *
   * @throws \Robo\Exception\TaskException
   * @throws \Exception
   */
  protected function getApplicationFromDrushRemote(string $id, string $env = 'prod'): string {
    $result = $this->taskDrush()
      ->alias("$id.$env")
      ->drush('status')
      ->options([
        'field' => 'application',
      ])
      ->printMetadata(FALSE)
      ->printOutput(TRUE)
      ->run();

    if (!$result->wasSuccessful()) {
      throw new \Exception('Unable to get current application with Drush.');
    }

    return trim($result->getMessage());
  }

  /**
   * Get the application for a site from the manifest.
   *
   * @param string $site
   *   The site URL as listed in the manifest.
   *
   * @return string
   *   The application name.
   */
  protected function getAppForSiteFromManifest(string $site): string {
    $manifest = $this->manifestToArray();
    foreach ($manifest as $app => $sites) {
      if (in_array($site, $sites)) {
        return $app;
      }
    }
    return '';
  }

  /**
   * Get the path to the manifest file.
   *
   * @return string
   *   The path to the manifest file.
   */
  protected function getManifestPath(): string {
    $root = $this->getConfigValue('repo.root');
    return "{$root}/blt/manifest.yml";
  }

  /**
   * Adds a $site to the $yaml array under the 'manifest'.
   *
   * @param array $manifest
   *   The yaml array to be added to.
   * @param string $app
   *   The app identifier under which to put the $site.
   * @param string $site
   *   The multisite identifier.
   */
  protected function addSiteToManifest(array &$manifest, string $app, string $site): void {
    if (!isset($manifest[$app])) {
      $manifest[$app] = [];
    }

    $manifest[$app][] = $site;
  }

  /**
   * Adds a $site to the $yaml array under the 'manifest'.
   *
   * @param array $manifest
   *   The yaml array to be removed from.
   * @param string $app
   *   The app identifier from which to remove the $site from.
   * @param string $site
   *   The multisite identifier.
   */
  protected function removeSiteFromManifest(array &$manifest, string $app, string $site): void {
    // If the designated app exists...
    if (isset($manifest[$app])) {

      // Look for the key in the array.
      if (($key = array_search($site, $manifest[$app])) !== FALSE) {

        // And unset it if we find it.
        unset($manifest[$app][$key]);
        $manifest[$app] = array_values($manifest[$app]);
      }

      // If the app is empty, remove the app.
      if (empty($manifest[$app])) {
        unset($manifest[$app]);
      }
    }
  }

  /**
   * Load the manifest file into an array.
   *
   * @return array
   *   The manifest array.
   */
  protected function manifestToArray(): array {
    return YamlMunge::parseFile($this->getManifestPath());
  }

  /**
   * Dump the manifest array to a file.
   *
   * @param array $manifest
   *   The manifest array.
   */
  protected function arrayToManifest($manifest): void {
    // Sort the apps.
    ksort($manifest);
    foreach ($manifest as &$app) {
      sort($app);
    }
    $this->taskWriteToFile($this->getManifestPath())
      ->text(Yaml::dump($manifest, 8, 2, Yaml::DUMP_OBJECT_AS_MAP))
      ->run();
  }

}
