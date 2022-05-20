# SiteNow Migrate

This module provides functionality to assist with writing migrations into the SiteNow platform.

## Extending SiteNow Migrate
### Create site migration module
- If the site does not already have a `modules` folder, create one. Example: `docroot/sites/iisc.uiowa.edu/modules`.
- Copy an existing site migrate module to the site `modules` folder, renaming it to the site subdomain + `_migrate`. Example:  `iisc_migrate`.
- Rename the `.info.yml` and `.install` files to use the module name you picked. Example: `iisc_migrate.info.yml`.
-
