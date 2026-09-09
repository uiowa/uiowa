# uiowa

The base application on Acquia Cloud for the University of Iowa.


# Getting Started

This project uses [Acquia Drupal Recommended Settings](https://github.com/acquia/drupal-recommended-settings) for its settings.php cascade, and a custom Symfony Console application (`./sn`, in `sitenow/`) for multisite provisioning, deployment, and reporting.

1. Ensure that your computer meets the minimum installation requirements for [DDEV](https://ddev.readthedocs.io/en/stable/#installation).
2. Request access to organization that owns the project repo in GitHub (if needed).
3. Request access to the Acquia Cloud Environment for your project (if needed).
4. Setup a SSH key that can be used for GitHub and the Acquia Cloud (you CAN use the same key).
    1. [Setup GitHub SSH Keys](https://help.github.com/articles/adding-a-new-ssh-key-to-your-github-account/)
    2. [Setup Acquia Cloud SSH Keys](https://docs.acquia.com/acquia-cloud/ssh/generate)
5. Clone the repository. By default, Git names this "origin" on your local.
    ```
    $ git clone git@github.com:uiowa/uiowa
    ```
----
# Local Environment
[Ddev](https://ddev.readthedocs.io/en/stable/) is used for the local environment. Follow their [docs](https://ddev.readthedocs.io/en/stable/#installation) to get it installed. Once installed, read up on [basic CLI](https://ddev.readthedocs.io/en/stable/users/cli-usage/) usage to understand how to manage the containers.

Once installed and started, you can either `ddev ssh` and run non-ddev CLI commands there, or run them on your host with `ddev CMD`. For example, `ddev sn sync:all` or `ddev composer install`.

## Workspaces
Yarn [workspaces](https://classic.yarnpkg.com/en/docs/workspaces) can be defined in the top-level package.json file. Each workspace can depend on other workspaces as well as define their own build script. You can run workspace build scripts on the web container with `ddev yarn workspace WORKSPACE_NAME run SCRIPT_NAME`. Every workspace build script gets run during continuous integration to build assets. The build assets are committed to the build artifact and deployed.

Workspaces that need to leverage uiowa/uids assets should depend on uids_base and not uiowa/uids directly. This is to ensure the version of uiowa/uids is strictly managed and because uids_base runs a build script that copies necessary assets into the build artifact. For example, fonts are available in uids_base which would not be available in the excluded node_modules directory.

## Databases
Ddev creates a database container that is accessible from the web container. You can access the database container [from your host](https://ddev.readthedocs.io/en/stable/users/topics/database_management/) as well using tools like [SequelPro](https://www.sequelpro.com/) or [TablePlus](https://tableplus.com/).

## Logging
As long as a site has a local settings file, it should be configured to show all warnings and errors to the screen. Other log messages can be viewed by running `ddev logs`.

### SiteNow Configuration
Save your [Acquia Cloud key and secret](https://docs.acquia.com/acquia-cloud/develop/api/auth/) in `~/.sitenow/credentials.yml`. This file lives in your home directory rather than the repository so it can never be committed. Do not share your key or secret with anyone.
```
acquia:
  key: foo
  secret: bar
```

Set the sites you work with locally in `sitenow/local.sites.yml`. This file is ignored by Git. It is the list `ddev sn sync:all` syncs and `ddev sn umi` installs, and it can be overridden per-run with `--sites`.
```
sites:
  - default
  - bar.uiowa.edu
  - foo.uiowa.edu
```

### Common Tasks
Multisites will not be able to bootstrap without a `local.settings.php` file. The `drush settings --uri=SITE` command (provided by Acquia Drupal Recommended Settings) will generate local settings files for a multisite.

Local overrides go directly in that site's `docroot/sites/SITE/settings/local.settings.php`, since it is not tracked in git. For example, to configure stage file proxy (`$config['stage_file_proxy.settings']['origin'] = 'https://sandbox.prod.drupal.uiowa.edu';`) add that line to the file directly.

The `ddev yarn frontend:build` command will install and compile frontend assets.

## Multisite Management
SiteNow provides host-side multisite commands through the `sn` CLI, including `multisite:create`. See [sitenow/README.md](sitenow/README.md).

Because the `.git` directory is not synced to the web container, `./sn` commands need to be run on your host machine instead of in the web container.

# Updating Dependencies
Before starting updates, make sure your local environment is on a feature branch created from the latest version of the default branch and synced with production by running `ddev sn sync:all`. After updating, certain scaffold files may need to be resolved/removed. For example, the htaccess patch might need to be regenerated if it does not apply to the new `.htaccess` file. Drupal core scaffolding may download default config files that we don't use like `docroot/sites/default/default.services.yml`. Different updates may require difference procedures.

## Updating core patched files

- Remove the `core_htaccess.patch` line from `post-drupal-scaffold-cmd` in the composer.json file and run `ddev composer install`.
- Commit the .htaccess change only with git but do not push.
- Re-add the line back to composer and re-run `ddev composer install`.
- Make edits to the .htaccess file.
- `git diff docroot/.htaccess > patches/core_htaccess.patch`
- Commit both the changed version and the patch. Push.
- Same method works for robots.txt and development_services.

## Configuration changes

Configuration tracked in the repository will need to be exported before deployment. To ensure configuration is exported correctly, manually sync a site from production using Drush. Then run database updates and export any configuration changes. Add and commit the config changes and then run another `ddev sn sync:all` to check for any further config discrepancies. If there are none, proceed with code deployment as per usual.

## Testing Dependencies
Testing a uids change in uiowa:
1. Update the hash with the uids commit you wish you test in the uids_base package.json file: "@uiowa/uids4": "uiowa/uids4#[Enter hash here]"
2. Then run `yarn upgrade @uiowa/uids4`
3. `rm -rf ./node_modules`
4. `yarn cache clean`
5. `yarn install`
6. `yarn workspace uids_base gulp --development`

## Core
Run `composer update "drupal/core-*" --with-all-dependencies`.

## Contrib
You can run `composer update package/name` to update additional dependencies. The output from the Composer commands can be used as the long text for commit messages. Ideally, each package update would be one commit for clarity and easier reverting.

### Locked Packages
The packages below are locked at specific SHAs and will not update using the method described above. They should be periodically checked for new stable releases and updated, if viable.

| Package                      | Reason                                                                                                                       |
|------------------------------|------------------------------------------------------------------------------------------------------------------------------|
| uiowa/block_content_template | Forked from a deprecated project.                                                                                            |
| drupal/theme_permission      | Using D10 compatibility patch that is compatible with dev version. Waiting for D10 release.                                  |




# Redirects
Redirects can be added to the docroot/.htaccess file. The .htaccess file be will deployed to all applications, regardless of the domain. Therefore, creating per-site redirects using the Redirect module is preferred.

Note that too many .htaccess redirects can incur a performance hit. See the [Acquia redirect documentation](https://docs.acquia.com/acquia-cloud/manage/htaccess/) for more information and examples.

Ideally, redirects in .htaccess would only exist temporarily. Check the commit history of that file using a command similar to: `git log --before="6 months ago" --grep="redirect" -- docroot/.htaccess` to see how old a redirect is.

# Resources
Additional [Acquia Drupal Recommended Settings documentation](https://github.com/acquia/drupal-recommended-settings) may be useful for the settings.php cascade. See [sitenow/README.md](sitenow/README.md) for the `./sn` command reference.
