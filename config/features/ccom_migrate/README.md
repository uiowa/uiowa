# Functionality
* Enables the `ccom_migrate` and dependent modules which can be used for migrations on CCOM sites.

## Getting started

All CCOM migrations must be run locally, and so require both a functional DevDesktop setup for working with D7 sites, as well as a local setup for Sitenow V3.

### Sync both source and destination sites locally

#### Sync the D7 site in DevDesktop

* Sync the database from Prod (files do not need to be synced)

#### Sync the V3 site using Docker

* Sync the site with `ddev blt ds --site=sitename`
  * The sitename is the full domain, not the drush alias or production domain (eg "surgery.medicine.uiowa.edu")
* Sync the site's files with `drush rsync @site.prod:%files @site.local:%files`
  * Replace "site" with the site alias (eg @medicinesurgery.prod)

### Activate and set up the CCOM Migrate feature split

All CCOM migrations are contained within the CCOM Migrate split, which will also turn on necessary pieces of Sitenow Migrate.

`ddev drush @site.local config-split:activate ccom_migrate`

Log into the site and visit the configuration page for Sitenow Migrate (this is the same form for both Sitenow Migrate and CCOM Migrate, and controls settings for both).

`ddev drush @site.local uli /admin/config/sitenow/sitenow-migrate`

Complete the form using the help text and information from Dev Desktop, including the Public Files Path field using the format like "sites/**medicine.uiowa.edu.internalmedicine**/files" (no leading or trailing slash, and the middle section matching the multisite designation in Dev Desktop). This should match what is seen at /admin/config/media/file-system if logged into the D7 CCOM site.

### Run the registered migrations

You can check that the migrations have properly registered, and their status, at any point using
`ddev drush @site.local ms`

Run the migration(s)
`ddev drush @site.local mim d7_file`
`ddev drush @site.local mim ccom_article`
`ddev drush @site.local mim ccom_article_redirects`

A `--limit=X` flag can be added to migrate only X items, rather than the full migration

Migrations can be rolled back using
`ddev drush @site.local mr d7_file`

Notes:
 * The `d7_file` migration should ONLY be run prior to any other migrations. If it is run after another migration, you will get duplicate files. If it is run prior to others, the migrated files will be used for subsequent migrations (eg the articles migration).
 * The `d7_page`, `d7_page_redirects`, and `d7_person` migrations should be considered deprecated and experimental only until further notice.

### Sync things back up

After the migration has completed, turn the split back off and run a config import.
`ddev drush @site.local config-split:deactivate ccom_migrate && ddev drush @site.local cim`

Sync files and database back up to production
`ddev drush rsync @site.local:%files @site.prod:%files`
`ddev drush sql-sync @site.local @site.prod`
