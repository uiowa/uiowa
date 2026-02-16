<?php

/**
 * @file
 * Global settings for every multisite.
 *
 * This is the single entry point for the SiteNow settings cascade. It handles
 * environment detection, configuration splits, file paths, logging, and the
 * include ordering for early, per-site, CI, and local settings files.
 *
 * Required from each site's settings.php.
 */

use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use Acquia\DrupalEnvironmentDetector\FilePaths;
use Drupal\Core\Installer\InstallerKernel;

// Prevent access to uninstalled sites through the web interface.
if (InstallerKernel::installationAttempted() && php_sapi_name() != 'cli') {
  exit;
}

/**
 * @var string $site_path
 *   Always set and exposed by the Drupal Kernel.
 */
$repo_root = dirname(DRUPAL_ROOT);
$site_name = AcquiaDrupalEnvironmentDetector::getSiteName($site_path);
$ah_group = AcquiaDrupalEnvironmentDetector::getAhGroup() ?: 'local';
$ah_env = AcquiaDrupalEnvironmentDetector::getAhEnv() ?: 'local';
$is_ah_env = AcquiaDrupalEnvironmentDetector::isAhEnv();
$is_ci = (bool) getenv('CI');

// ============================================================================
// Reverse proxy / HTTPS detection.
//
// On Acquia Cloud, requests pass through load balancers before reaching
// Drupal. This section ensures Drupal sees the real client IP instead of the
// load balancer's IP. Without it, logging, rate limiting, and access rules
// would all reference the wrong address.
//
// $trusted_reverse_proxy_ips is populated by Acquia's settings include
// (loaded in the cascade below). Locally, it's empty and this section is
// effectively a no-op.
// ============================================================================

// Normalize $trusted_reverse_proxy_ips — may not be set or could be a string.
$trusted_reverse_proxy_ips = isset($trusted_reverse_proxy_ips) ? $trusted_reverse_proxy_ips : '';
if (!is_array($trusted_reverse_proxy_ips)) {
  $trusted_reverse_proxy_ips = [];
}

// If the request arrived via HTTPS through a trusted proxy, tell PHP.
if (getenv('HTTP_X_FORWARDED_PROTO') === 'https'
  && getenv('REMOTE_ADDR')
  && in_array(getenv('REMOTE_ADDR'), $trusted_reverse_proxy_ips, TRUE)) {
  putenv("HTTPS=on");
}

// Build the chain of IPs the request passed through.
$x_ips = getenv('HTTP_X_FORWARDED_FOR') ? explode(',', getenv('HTTP_X_FORWARDED_FOR')) : [];
$x_ips = array_map('trim', $x_ips);

if (getenv('REMOTE_ADDR')) {
  $x_ips[] = getenv('REMOTE_ADDR');
}

// Register trusted proxy addresses so Drupal resolves the real client IP.
$settings['reverse_proxy_addresses'] = $settings['reverse_proxy_addresses'] ?? [];
$ip = array_pop($x_ips);
if ($ip) {
  // If the outermost IP is a known load balancer, mark it as a proxy.
  if (in_array($ip, $trusted_reverse_proxy_ips)) {
    if (!in_array($ip, $settings['reverse_proxy_addresses'])) {
      $settings['reverse_proxy_addresses'][] = $ip;
    }
    $settings['reverse_proxy'] = TRUE;

    // If the next IP in the chain is a private/internal address (e.g. AWS
    // internal routing), also add it as a trusted proxy.
    $ip = array_pop($x_ips);
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE)) {
      if (!in_array($ip, $settings['reverse_proxy_addresses'])) {
        $settings['reverse_proxy_addresses'][] = $ip;
      }
    }
  }
}

// ============================================================================
// Include cascade: Acquia Cloud settings, secrets.
// ============================================================================
if ($is_ah_env) {
  $settings_files = [];
  $settings_files[] = FilePaths::ahSettingsFile(AcquiaDrupalEnvironmentDetector::getAhGroup(), $site_name);
  // Secrets stored outside version control on Acquia file system.
  $ah_files_root = AcquiaDrupalEnvironmentDetector::getAhFilesRoot();
  $settings_files[] = $ah_files_root . '/secrets.settings.php';
  $settings_files[] = $ah_files_root . "/$site_name/secrets.settings.php";

  foreach ($settings_files as $settings_file) {
    if (file_exists($settings_file)) {
      require $settings_file;
    }
  }
}

// ============================================================================
// Memcache.
// ============================================================================
$memcache_settings_file = $repo_root . '/vendor/acquia/memcache-settings/memcache.settings.php';
if (file_exists($memcache_settings_file)) {
  require_once $memcache_settings_file;
}

// ============================================================================
// Configuration management.
// ============================================================================

// Unset the VCS config directory so cim/cex default to sync.
if (isset($config_directories['vcs'])) {
  unset($config_directories['vcs']);
}

$settings['config_sync_directory'] = $repo_root . "/config/default";

// Activate config splits by environment.
$split_filename_prefix = 'config_split.config_split';
$split_envs = [
  'local' => AcquiaDrupalEnvironmentDetector::isLocalEnv(),
  'dev' => AcquiaDrupalEnvironmentDetector::isAhDevEnv(),
  'stage' => AcquiaDrupalEnvironmentDetector::isAhStageEnv(),
  'prod' => AcquiaDrupalEnvironmentDetector::isAhProdEnv(),
  'ci' => $is_ci,
  'ode' => AcquiaDrupalEnvironmentDetector::isAhOdeEnv(),
  'ah_other' => $is_ah_env && !AcquiaDrupalEnvironmentDetector::isAhDevEnv() && !AcquiaDrupalEnvironmentDetector::isAhStageEnv() && !AcquiaDrupalEnvironmentDetector::isAhOdeEnv() && !AcquiaDrupalEnvironmentDetector::isAhProdEnv(),
];
foreach ($split_envs as $split_env => $status) {
  $config["$split_filename_prefix.$split_env"]['status'] = $status;
}

// Per-site config split.
$config["$split_filename_prefix.$site_name"]['status'] = TRUE;

// ============================================================================
// Logging.
// ============================================================================
if (AcquiaDrupalEnvironmentDetector::isAhProdEnv() || AcquiaDrupalEnvironmentDetector::isAhStageEnv()) {
  $config['system.logging']['error_level'] = 'hide';
}

if (AcquiaDrupalEnvironmentDetector::isLocalEnv() || AcquiaDrupalEnvironmentDetector::isAhDevEnv()) {
  error_reporting(E_ALL);
  ini_set('display_errors', TRUE);
  ini_set('display_startup_errors', TRUE);
}

// ============================================================================
// Filesystem.
// ============================================================================
$settings['file_public_path'] = "sites/$site_name/files";

if ($is_ah_env) {
  $settings['file_private_path'] = AcquiaDrupalEnvironmentDetector::getAhFilesRoot() . "/sites/$site_name/files-private";
}

// ============================================================================
// Deployment identifier.
// ============================================================================
$settings['deployment_identifier'] = \Drupal::VERSION;
$deploy_id_file = $repo_root . '/deployment_identifier';
if (file_exists($deploy_id_file)) {
  $settings['deployment_identifier'] = file_get_contents($deploy_id_file);
}

// ============================================================================
// SiteNow customizations.
// ============================================================================

// Environment indicator toolbar color and name.
switch ($ah_env) {
  case 'local':
    $settings['simple_environment_indicator'] = '#00664F local';
    break;

  case 'dev':
    $settings['simple_environment_indicator'] = '#00558C dev';
    break;

  case 'test':
    $settings['simple_environment_indicator'] = '#BD472A test';
    break;

  case 'prod':
    $settings['simple_environment_indicator'] = '#63666A prod';
    break;
}

/**
 * A custom theme for the offline page.
 *
 * This applies when the site is explicitly set to maintenance mode through the
 * administration page or when the database is inactive due to an error.
 * The template file should also be copied into the theme. It is located inside
 * 'core/modules/system/templates/maintenance-page.html.twig'.
 *
 * Note: This setting does not apply to installation and update pages.
 */
$settings['maintenance_theme'] = 'uids_base';

// Hash salt unique per site.
$settings['hash_salt'] = hash('sha256', $ah_group . $ah_env . $site_name);

// Compatibility with Acquia Platform Email for Symfony Mailer module.
// See https://docs.acquia.com/cloud-platform/manage/platform-email/faq/#can-i-use-symfony-mailer-with-platform-email
$settings['mailer_sendmail_commands'] = [
  '/usr/sbin/sendmail -t',
];

if ($ah_env !== 'local') {
  $config['symfony_mailer.mailer_transport.sendmail']['configuration']['query']['command'] = '/usr/sbin/sendmail -t';
}

// Set recommended New Relic configuration.
// @see: https://docs.acquia.com/acquia-cloud/monitor/apm/#recommended-configuration-settings
ini_set('newrelic.loglevel', 'error');

if (extension_loaded('newrelic')) {
  newrelic_set_appname("{$site_name};{$ah_group}.{$ah_env}", '', 'true');
}

// Increase 'max_input_vars' for large menu pages so webmasters can save changes.
if (isset($_SERVER['REQUEST_URI']) && str_contains($_SERVER['REQUEST_URI'],
    'admin/structure/menu')) {
  ini_set('max_input_vars', '5000');
}

// Under Acquia Cloud, override the Webform temp directory.
// See https://www.drupal.org/project/webform/issues/2980276
if ($ah_env !== 'local') {
  $config['webform.settings']['export']['temp_directory'] = "/mnt/gfs/{$_ENV['AH_SITE_GROUP']}.{$_ENV['AH_SITE_ENVIRONMENT']}/tmp";
}

// ============================================================================
// Per-site includes.
// ============================================================================
$per_site_includes = DRUPAL_ROOT . "/sites/$site_name/settings/includes.settings.php";
if (file_exists($per_site_includes)) {
  require $per_site_includes;
}

// ============================================================================
// CI settings.
// ============================================================================
if ($is_ci) {
  // Project CI settings (global and per-site).
  $ci_settings_files = [
    DRUPAL_ROOT . "/sites/settings/ci.settings.php",
    DRUPAL_ROOT . "/sites/$site_name/settings/ci.settings.php",
  ];
  foreach ($ci_settings_files as $ci_file) {
    if (file_exists($ci_file)) {
      require $ci_file;
    }
  }
}

// ============================================================================
// Local settings.
// Note: isLocalEnv() returns TRUE for all non-Acquia environments, including
// CI. This means local settings files also run during CI builds.
// ============================================================================
if (AcquiaDrupalEnvironmentDetector::isLocalEnv()) {
  $local_settings_files = [
    DRUPAL_ROOT . '/sites/settings/local.settings.php',
    DRUPAL_ROOT . "/sites/$site_name/settings/local.settings.php",
  ];
  foreach ($local_settings_files as $local_file) {
    if (file_exists($local_file)) {
      require $local_file;
    }
  }
}
