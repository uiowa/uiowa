<?php

namespace Uiowa;

use Acquia\Blt\Robo\Tasks\LoadTasks;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

/**
 * OPE.
 */
trait UtilsTrait {

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
   */
  protected function getApplicationFromDrushRemote(string $id, string $env = 'prod', bool $throwable = TRUE): string {
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
      if ($throwable) {
        throw new \Exception('Unable to get current application with Drush.');
      }
      else {
        return FALSE;
      }
    }

    return trim($result->getMessage());
  }

  /**
   * Adds a $site to the $yaml array under the 'manifest'.
   *
   * @param array $yaml
   *    The yaml array to be added to.
   * @param string $site
   *   The multisite identifier.
   * @param string $app
   *   The app identifier under which to put the $site.
   *
   * @return array
   *    The yaml array.
   */
  protected function addSiteToManifest(array $yaml, string $site, string $app): array {
    if (isset($yaml['manifest'][$app])) {
      $yaml['manifest'][$app][] = $site;
      return $yaml;
    }

    $yaml['manifest'][$app] = [];
    $yaml['manifest'][$app][] = $site;
    return $yaml;
  }

  /**
   * Adds a $site to the $yaml array under the 'manifest'.
   *
   * @param array $yaml
   *    The yaml array to be removed from.
   * @param string $site
   *   The multisite identifier.
   * @param string $app
   *   The app identifier from which to remove the $site from.
   *
   * @return array
   *    The yaml array.
   */
  protected function removeSiteFromManifest(array $yaml, string $site, string $app): array {
    // If the designated app exists...
    if (isset($yaml['manifest'][$app])) {

      // Look for the key in the array.
      if (($key = array_search($site, $yaml['manifest'][$app])) !== false) {

        // And unset it if we find it.
        unset($yaml['manifest'][$app][$key]);
        $yaml['manifest'][$app] = array_values($yaml['manifest'][$app]);
      }

      // If the app isn't empty, return the yaml.
      if(count($yaml['manifest'][$app]) > 0) {
        return $yaml;
      }

      // If it is, remove the app, and re-index the manifest.
      unset($yaml['manifest'][$app]);
      return $yaml;
    }

    return $yaml;
  }

  /**
   * Dump a yml array $contents formatted as a yml map into a file at $path.
   *
   * @param string $path
   *    The path to the yml file.
   * @param array $contents
   *    The yml array to be dumped as a map into the file at $path.
   */
  protected function ymlMapDump(string $path, array $contents) {
    $fs = new Filesystem();
    $yaml_string = Yaml::dump($contents, 8, 2, Yaml::DUMP_OBJECT_AS_MAP);
    $fs->dumpFile($path, $yaml_string);
  }

  /**
   * Parse a yml array formatted as a yml map from the file at $path.
   *
   * @param string $path
   *    The path to the yml file.
   *
   * @return array
   *    The contents of the yml file at $path in array form.
   */
  protected function ymlMapParse($path) {
    return Yaml::parse(file_get_contents($path, Yaml::PARSE_OBJECT_FOR_MAP));
  }

}
