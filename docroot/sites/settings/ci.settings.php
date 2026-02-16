<?php

/**
 * @file
 * Common settings for CI environments.
 */

$config['system.logging']['error_level'] = 'verbose';

$dir = dirname(DRUPAL_ROOT);
$settings['file_private_path'] = $dir . '/files-private';
$settings['trusted_host_patterns'] = [
  '^.+$',
];

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

$settings['skip_permissions_hardening'] = TRUE;
