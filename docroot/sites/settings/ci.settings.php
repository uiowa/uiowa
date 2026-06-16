<?php

/**
 * @file
 * CI environment settings (GitHub Actions, local testing, etc).
 *
 * This file provides CI settings without BLT dependencies.
 * Used for GitHub Actions and local CI testing with `ddev ci`.
 */

// Verbose error logging for debugging.
$config['system.logging']['error_level'] = 'verbose';

// File paths.
$dir = dirname(DRUPAL_ROOT);
$settings['file_private_path'] = $dir . '/files-private';

// Database configuration from environment.
// Set by scripts/ci/setup.sh via SIMPLETEST_DB environment variable.
if ($simpletest_db = getenv('SIMPLETEST_DB')) {
  $databases['default']['default'] = [
    'database' => '',
    'username' => '',
    'password' => '',
    'host' => '',
    'port' => '',
    'namespace' => 'Drupal\\Core\\Database\\Driver\\mysql',
    'driver' => 'mysql',
    'prefix' => '',
    'collation' => 'utf8mb4_general_ci',
  ];

  // Parse the database URL (format: mysql://user:pass@host/database).
  $db_url = parse_url($simpletest_db);
  $databases['default']['default']['database'] = ltrim($db_url['path'] ?? 'drupal', '/');
  $databases['default']['default']['username'] = $db_url['user'] ?? 'drupal';
  $databases['default']['default']['password'] = $db_url['pass'] ?? 'drupal';
  $databases['default']['default']['host'] = $db_url['host'] ?? '127.0.0.1';
  $databases['default']['default']['port'] = $db_url['port'] ?? '3306';
}
else {
  // Fallback database configuration.
  $databases['default']['default'] = [
    'database' => 'drupal',
    'username' => 'drupal',
    'password' => 'drupal',
    'host' => '127.0.0.1',
    'port' => '3306',
    'namespace' => 'Drupal\\Core\\Database\\Driver\\mysql',
    'driver' => 'mysql',
    'prefix' => '',
  ];
}

// Trusted host patterns for CI environments.
$settings['trusted_host_patterns'] = [
  '^.+$',  // Allow all in CI (BLT default)
];

// Skip file system permissions hardening in CI.
// Prevents issues with version-controlled files.
$settings['skip_permissions_hardening'] = TRUE;

// Disable CSS/JS aggregation for easier debugging.
$config['system.performance']['css']['preprocess'] = FALSE;
$config['system.performance']['js']['preprocess'] = FALSE;

// Disable caching for test consistency.
$settings['cache']['bins']['render'] = 'cache.backend.null';
$settings['cache']['bins']['dynamic_page_cache'] = 'cache.backend.null';
$settings['cache']['bins']['page'] = 'cache.backend.null';

// Set hash salt for consistent testing.
$settings['hash_salt'] = 'ci-testing-hash-salt-do-not-use-in-production';

// Disable automatic cron runs.
$config['automated_cron.settings']['interval'] = 0;
