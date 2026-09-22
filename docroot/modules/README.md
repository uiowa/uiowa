# Modules

## Directories

- `contrib/` holds contributed modules from drupal.org, installed by Composer
  (`type:drupal-module`).
- `custom/` holds modules shared by every SiteNow site and tracked in this
  repository. The `sitenow_*` modules are installation profile features.
- `uiowa/` holds University of Iowa modules installed by Composer
  (`type:drupal-custom-module`). This directory is gitignored, so edits here are
  not tracked. Changes belong in the module's own repository.

Modules that belong to a single site live in `docroot/sites/{site}/modules/`.

## Removing a module

Removing a module takes two deploys. Drop the usage first, meaning config
splits, view plugins, field formatters and code references, and leave the
package in `composer.json`. Remove the package only once that has fully
deployed.

Config import and `updb` run per site, sequentially, across the whole fleet.
Dropping the package in the same deploy that removes its usage leaves every
site not yet processed running new code without the module while its active
config still lists it. Those sites fatal until their own import runs, assuming
the deploy gets that far.

Patches against the module can ship with the usage-removal PR, since no site
runs the patched code path after its config import.

The same two-deploy pattern applies to swapping one module for another.
