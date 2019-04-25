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

// Directory aliases for sitenow.uiowa.edu (default).
$sites['sitenow.uiowa.lndo.site'] = 'default';
$sites['sitenow.dev.drupal.uiowa.edu'] = 'default';
$sites['sitenow.test.drupal.uiowa.edu'] = 'default';
$sites['sitenow.prod.drupal.uiowa.edu'] = 'default';
