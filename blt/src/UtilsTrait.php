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
        return "unknown";
      }
    }

    return trim($result->getMessage());
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
