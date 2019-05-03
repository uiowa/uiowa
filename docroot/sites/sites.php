<?php

/**
 * @file
 * Multisite directory aliases.
 *
 * These may need to be more restrictive depending on how acquia_purge
 * determines the purge domains for multisites.
 *
 * @see https://www.drupal.org/project/acquia_purge/issues/3050554
 * @see example.sites.php
 */

// Require local sites.php if available.
if (file_exists(__DIR__ . '/sites.local.php')) {
  require __DIR__ . '/sites.local.php';
}

// Directory aliases for sitenow.uiowa.edu (default).
$sites['sitenow.uiowa.lndo.site'] = 'default';
$sites['sitenow.dev.drupal.uiowa.edu'] = 'default';
$sites['sitenow.test.drupal.uiowa.edu'] = 'default';
$sites['sitenow.prod.drupal.uiowa.edu'] = 'default';
$sites['sitenow.uiowa.edu'] = 'default';

// Directory aliases for hr.uiowa.edu.
$sites['hr.uiowa.lndo.site'] = 'hr';
$sites['hr-d8.dev.drupal.uiowa.edu'] = 'hr';
$sites['hr-d8.test.drupal.uiowa.edu'] = 'hr';
$sites['hr-d8.prod.drupal.uiowa.edu'] = 'hr';
$sites['hr.uiowa.edu'] = 'hr';

// Directory aliases for callctr.dentistry.uiowa.edu.
$sites['dentistrycallctr.uiowa.lndo.site'] = 'dentistrycallctr';
$sites['dentistrycallctr.dev.drupal.uiowa.edu'] = 'dentistrycallctr';
$sites['dentistrycallctr.test.drupal.uiowa.edu'] = 'dentistrycallctr';
$sites['dentistrycallctr.prod.drupal.uiowa.edu'] = 'dentistrycallctr';
$sites['callctr.dentistry.uiowa.edu'] = 'dentistrycallctr';