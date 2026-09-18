# Modules

- `contrib/` holds contributed modules from drupal.org, installed by Composer
  (`type:drupal-module`).
- `custom/` holds modules shared by every SiteNow site and tracked in this
  repository. The `sitenow_*` modules are installation profile features.
- `uiowa/` holds University of Iowa modules installed by Composer
  (`type:drupal-custom-module`). This directory is gitignored, so edits here are
  not tracked. Changes belong in the module's own repository.

Modules that belong to a single site live in `docroot/sites/{site}/modules/`.
