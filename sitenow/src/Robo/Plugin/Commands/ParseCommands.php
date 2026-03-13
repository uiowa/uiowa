<?php

namespace SiteNow\Robo\Plugin\Commands;

use Acquia\Blt\Robo\Common\YamlMunge;
use Robo\Tasks;
use Robo\Symfony\ConsoleIO;

/**
 * Commands for parsing files.
 */
class ParseCommands extends Tasks {

  /**
   * Test command.
   */
  public function hello(ConsoleIO $io, $world) {
    $io->say("Hello, $world");
  }

  /**
   * Get a list of custom routes.
   */
  public function routesCustom(ConsoleIO $io) {
    // Search a list of paths for *.routing.yml files and print a list.
    $paths = [
      'modules/custom',
      'modules/uiowa',
      'sites/**/modules',
    ];
    $routes = [];
    foreach ($paths as $path) {
      // Find all routing.yml files in the given path and add them to the list.
      $files = glob(DRUPAL_ROOT . '/' . $path . '/*/*.routing.yml');
      foreach ($files as $file) {
        // Parse out the module name from the file path.
        $parts = explode('/', $file);
        // The index is the second to last part of the path.
        $moduleName = $parts[count($parts) - 2];
        $yaml = YamlMunge::parseFile($file);
        foreach ($yaml as $routeName => $route) {
          // Skip `route_callbacks` key for now.
          if (!isset($route['path'])) {
            continue;
          }
          $routes[] = "$path,$moduleName,$routeName,{$route['path']}";
        }
      }
    }

    sort($routes);
    $io->text($routes);
  }

}
