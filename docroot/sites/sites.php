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

// Directory aliases for the default site.
$sites['demo.dev.drupal.uiowa.edu'] = 'default';
$sites['demo.stage.drupal.uiowa.edu'] = 'default';
$sites['demo.prod.drupal.uiowa.edu'] = 'default';

// Directory aliases for hr.uiowa.edu.
$sites['hr.dev.drupal.uiowa.edu'] = 'hr.uiowa.edu';
$sites['hr.stage.drupal.uiowa.edu'] = 'hr.uiowa.edu';
$sites['hr.prod.drupal.uiowa.edu'] = 'hr.uiowa.edu';
$sites['hr.uiowa.edu'] = 'hr.uiowa.edu';

// Directory aliases for callctr.dentistry.uiowa.edu.
$sites['dentistrycallctr.dev.drupal.uiowa.edu'] = 'callctr.dentistry.uiowa.edu';
$sites['dentistrycallctr.stage.drupal.uiowa.edu'] = 'callctr.dentistry.uiowa.edu';
$sites['dentistrycallctr.prod.drupal.uiowa.edu'] = 'callctr.dentistry.uiowa.edu';
$sites['callctr.dentistry.uiowa.edu'] = 'callctr.dentistry.uiowa.edu';

// Directory aliases for gis.sites.uiowa.edu.
$sites['sitesgis.dev.drupal.uiowa.edu'] = 'gis.sites.uiowa.edu';
$sites['sitesgis.stage.drupal.uiowa.edu'] = 'gis.sites.uiowa.edu';
$sites['sitesgis.prod.drupal.uiowa.edu'] = 'gis.sites.uiowa.edu';

// Directory aliases for protostudios.uiowa.edu.
$sites['protostudios.dev.drupal.uiowa.edu'] = 'protostudios.uiowa.edu';
$sites['protostudios.stage.drupal.uiowa.edu'] = 'protostudios.uiowa.edu';
$sites['protostudios.prod.drupal.uiowa.edu'] = 'protostudios.uiowa.edu';

// Directory aliases for www.dentistry.uiowa.edu.
$sites['dentistry.dev.drupal.uiowa.edu'] = 'www.dentistry.uiowa.edu';
$sites['dentistry.stage.drupal.uiowa.edu'] = 'www.dentistry.uiowa.edu';
$sites['dentistry.prod.drupal.uiowa.edu'] = 'www.dentistry.uiowa.edu';

// Directory aliases for uiventures.uiowa.edu.
$sites['uiventures.dev.drupal.uiowa.edu'] = 'uiventures.uiowa.edu';
$sites['uiventures.stage.drupal.uiowa.edu'] = 'uiventures.uiowa.edu';
$sites['uiventures.prod.drupal.uiowa.edu'] = 'uiventures.uiowa.edu';

// Directory aliases for brand.uiowa.edu.
$sites['brand.dev.drupal.uiowa.edu'] = 'brand.uiowa.edu';
$sites['brand.stage.drupal.uiowa.edu'] = 'brand.uiowa.edu';
$sites['brand.prod.drupal.uiowa.edu'] = 'brand.uiowa.edu';
