<?php

namespace Uiowa;

use Symfony\Component\Finder\Finder;

/**
 * Static class with various helper methods related multisite management.
 */
class Multisite {

  /**
   * Static class.
   */
  private function __construct() {}

  /**
   * Given a site directory name, return the standardized database name.
   *
   * @param string $dir
   *   The multisite directory, i.e. the URI without the scheme.
   *
   * @return string
   *   The AC database name.
   *
   * @throws \Exception
   */
  public static function getDatabaseName($dir) {
    if ($dir == 'default') {
      throw new \Exception('The default site is configured automatically by BLT.');
    }
    else {
      $db = str_replace(['.', '-'], '_', $dir);
    }

    return $db;
  }

  /**
   * Given a URI, create and return a unique identifier.
   *
   * Used for internal subdomain and Drush alias group name, i.e. file name.
   *
   * @param string $uri
   *   The multisite URI including the scheme.
   *
   * @return string
   *   The ID.
   *
   * @throws \Exception
   */
  public static function getIdentifier($uri) {
    // Parse the URL.
    if ($parsed = parse_url($uri)) {

      // Make a special exception for the default site and homepage. The
      // homepage ID would be uiowa and conflict with the uiowa app alias.
      if ($parsed['host'] == 'default') {
        $id = 'default';
      }
      elseif ($parsed['host'] === 'uiowa.edu') {
        $id = 'home';
      }
      elseif (substr($parsed['host'], -9) === 'uiowa.edu') {
        // Don't use the suffix if the host equals uiowa.edu.
        $id = substr($parsed['host'], 0, -10);

        // Reverse the subdomains.
        $parts = array_reverse(explode('.', $id));

        // Unset the www subdomain - considered the same site.
        $key = array_search('www', $parts);
        if ($key !== FALSE) {
          unset($parts[$key]);
        }
        $id = implode('', $parts);
      }
      else {
        // This site has a non-uiowa.edu TLD.
        $parts = explode('.', $parsed['host']);

        // Unset the www subdomain - considered the same site.
        $key = array_search('www', $parts);
        if ($key !== FALSE) {
          unset($parts[$key]);
        }

        // Pop off the suffix to be used later as a prefix.
        $extension = array_pop($parts);

        // Reverse the subdomains.
        $parts = array_reverse($parts);
        $id = $extension . '-' . implode('', $parts);
      }

      return $id;
    }
    else {
      throw new \Exception("Unable to parse URL {$uri}.");
    }
  }

  /**
   * Given a multisite ID, return an array of internal domains.
   *
   * @param string $id
   *   The multisite identifier.
   *
   * @return array
   *   Internal domains keyed by AC environment machine name.
   */
  public static function getInternalDomains($id) {
    return [
      'local' => "{$id}.uiowa.ddev.site",
      'dev' => "{$id}.dev.drupal.uiowa.edu",
      'test' => "{$id}.stage.drupal.uiowa.edu",
      'prod' => "{$id}.prod.drupal.uiowa.edu",
    ];
  }

  /**
   * Find all multisites in the application root, excluding default.
   *
   * @param string $root
   *   The root of the application to find multisites in.
   *
   * @return array
   *   An array of sites.
   */
  public static function getAllSites($root) {
    $finder = new Finder();

    $dirs = $finder
      ->in("{$root}/docroot/sites/")
      ->directories()
      ->depth('< 1')
      ->exclude(['default', 'g', 'settings', 'simpletest'])
      ->sortByName();

    $sites = [];
    foreach ($dirs->getIterator() as $dir) {
      $sites[] = $dir->getRelativePathname();
    }

    return $sites;
  }

  /**
   * Get SSL search strings based on a URI host.
   *
   * @param string $host
   *   The host, i.e. the multisite directory.
   */
  public static function getSslParts($host) {
    // Explode by domain and limit to two parts. Search for wildcard coverage.
    $host_parts = explode('.', $host, 2);

    // If the host is one subdomain off uiowa.edu or a vanity domain,
    // search for the host instead.
    // Ex. foo.uiowa.edu -> search for foo.uiowa.edu.
    // Ex. foo.com -> search for foo.com.
    if ($host_parts[1] == 'uiowa.edu' || !stristr($host_parts[1], '.')) {
      $sans = $host;
    }
    else {
      // Ex. foo.bar.uiowa.edu -> search for *.bar.uiowa.edu.
      // Ex. foo.bar.baz.uiowa.edu -> search for *.bar.baz.uiowa.edu.
      $sans = '*.' . $host_parts[1];
    }

    // Consider the parent domain related and search for it since it could
    // be covered with one SSL SAN while double subdomains cannot. However,
    // uiowa.edu is the exception because we cannot cover *.uiowa.edu.
    $related = ($host_parts[1] == 'uiowa.edu') ? NULL : $host_parts[1];

    return [
      'sans' => $sans,
      'related' => $related,
    ];
  }

}
