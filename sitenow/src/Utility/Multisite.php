<?php

namespace SiteNow\Utility;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Static class with various helper methods related multisite management.
 */
class Multisite {

  /**
   * Static class.
   */
  private function __construct() {}

  /**
   * Parsed alias files, keyed by "<alias dir>::<name>".
   *
   * A fleet run can ask for the same file hundreds of times (once per site
   * per environment lookup), so each one is parsed at most once per request.
   *
   * @var array<string, array>
   */
  private static array $aliasFileCache = [];

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
      throw new \Exception('The default site is configured automatically by DRS.');
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

  /**
   * Read an application or site's drush alias file.
   *
   * @param string $aliasDir
   *   Absolute path to the directory holding the alias files: drush/sites
   *   under the repository root, unless a caller (e.g. FleetRunner, for
   *   tests) points elsewhere.
   * @param string $name
   *   The alias file's basename, without extension: an application
   *   (AH_SITE_GROUP, e.g. 'uiowa09') or a site identifier (e.g.
   *   'accessibility').
   *
   * @return array
   *   Parsed alias definitions keyed by environment (local, dev, test,
   *   prod), or an empty array if the alias file does not exist or fails
   *   to parse.
   */
  public static function getAliasFile(string $aliasDir, string $name): array {
    $key = "{$aliasDir}::{$name}";

    if (!array_key_exists($key, self::$aliasFileCache)) {
      $parsed = [];
      $path = "{$aliasDir}/{$name}.site.yml";

      if (is_file($path)) {
        try {
          $parsed = Yaml::parseFile($path) ?? [];
        }
        catch (ParseException) {
          // A malformed alias file resolves to no environments rather than
          // crashing every caller that reads it.
        }
      }

      self::$aliasFileCache[$key] = is_array($parsed) ? $parsed : [];
    }

    return self::$aliasFileCache[$key];
  }

  /**
   * Read one environment out of an application or site's drush alias file.
   *
   * @param string $aliasDir
   *   Absolute path to the directory holding the alias files.
   * @param string $name
   *   The alias file's basename, without extension.
   * @param string $env
   *   The drush alias environment: local, dev, test, or prod.
   *
   * @return array|null
   *   The environment's definition (uri, user, host, etc.), or NULL when the
   *   alias file or the requested environment is missing.
   */
  public static function getAliasEnv(string $aliasDir, string $name, string $env): ?array {
    $definition = static::getAliasFile($aliasDir, $name)[$env] ?? NULL;

    return is_array($definition) ? $definition : NULL;
  }

  /**
   * Resolve Acquia Cloud's own name for an application's environment.
   *
   * Drush aliases always key the middle environment 'test', but Acquia
   * Cloud does not: it calls that environment 'test' on uiowa01-06 and
   * 'stage' on uiowa07-09. Every other environment name is uniform. The
   * alias's `user` field records the Acquia name we need, e.g.
   * `user: uiowa09.stage` versus `user: uiowa04.test`. This is the single
   * source of truth for the divergence; callers that need to match an
   * Acquia API environment name should resolve it through here rather than
   * assuming 'test' or hardcoding the 'stage' exception.
   *
   * @param string $aliasDir
   *   Absolute path to the directory holding the alias files.
   * @param string $name
   *   The alias file's basename, without extension.
   * @param string $env
   *   The drush alias environment: local, dev, test, or prod.
   *
   * @return string|null
   *   The Acquia Cloud environment name, or NULL if the alias file or the
   *   requested environment's user field is missing.
   */
  public static function getCloudEnvName(string $aliasDir, string $name, string $env): ?string {
    $user = static::getAliasEnv($aliasDir, $name, $env)['user'] ?? NULL;

    if (!is_string($user) || $user === '') {
      return NULL;
    }

    // The patterns are like "uiowa09.stage", so we can just take
    // the substring after the period.
    return substr($user, strrpos($user, '.') + 1);
  }

  /**
   * Determine whether a string is a valid multisite host.
   *
   * Accepts a lowercase, dot-separated domain of two or more labels. Each
   * label starts and ends with an alphanumeric character and may contain
   * hyphens internally. Rejects uppercase, underscores, and leading or
   * trailing dots or hyphens.
   *
   * @param string $host
   *   The candidate host, i.e. the URI without the scheme.
   *
   * @return bool
   *   TRUE if the host is well-formed.
   */
  public static function isValidHost($host) {
    return (bool) preg_match(
      '/^[a-z0-9]([a-z0-9\-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9\-]*[a-z0-9])?)+$/',
      $host
    );
  }

}
