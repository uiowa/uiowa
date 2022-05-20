# SiteNow Migrate

This module provides functionality to assist with writing migrations into the SiteNow platform.

## Extending SiteNow Migrate
### Create site migration module
- If the site does not already have a `modules` folder, create one. Example: `docroot/sites/iisc.uiowa.edu/modules`.
- Copy an existing site migrate module to the site `modules` folder, renaming it to the site subdomain + `_migrate`. Example:  `iisc_migrate`.
- Rename the `.info.yml` and `.install` files to use the module name you picked. Example: `iisc_migrate.info.yml`.
-

### YML migration definitions

#### Process plugins
- `create_media_from_file_field`
  - Takes a D7 image field and converts to a D8 image field, including copying the source file (if needed) and creating an associated media entity.
- `extract_summary`:
  - Takes a formatted field w/summary and extracts only the summary, if it exists, or constructs a summary from the body value.
  - `length` option allows for specifying the length of the truncated summary, if it is constructed from the body value. Defaults to `400`.
