<?php

namespace SiteNow\Install;

/**
 * How far along a multisite's Drupal installation is.
 *
 * The distinction that matters is Absent vs. Partial. A site whose install died
 * partway already has a config table, so a "does the config table exist" test
 * reads it as installed and never revisits it. Separating the two is what lets
 * an incomplete install be healed instead of skipped forever.
 */
enum InstallStatus: string {

  // The site cannot be installed from here: no site directory, or its database
  // belongs to a different application.
  case Unavailable = 'unavailable';

  // Drupal has never been installed. No config table.
  case Absent = 'absent';

  // An install started and never finished. Drupal records install_task as
  // 'done' only in install_finished(), after every install task has run.
  case Partial = 'partial';

  // A complete install. Post-install reconciliation may still be needed.
  case Installed = 'installed';

}
