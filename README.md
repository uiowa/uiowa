# uiowa

The base application on Acquia Cloud for the University of Iowa.

# Getting Started

This project is based on BLT, an open-source project template and tool that enables building, testing, and deploying Drupal installations following Acquia Professional Services best practices. While this is one of many methodologies, it is our recommended methodology.

1. Review the [Required / Recommended Skills](https://docs.acquia.com/blt/developer/skills/) for working with a BLT project.
2. Ensure that your computer meets the minimum installation requirements (and then install the required applications). See the [System Requirements](https://docs.acquia.com/blt/install/#general-requirements).
3. Request access to organization that owns the project repo in GitHub (if needed).
4. Request access to the Acquia Cloud Environment for your project (if needed).
5. Setup a SSH key that can be used for GitHub and the Acquia Cloud (you CAN use the same key).
    1. [Setup GitHub SSH Keys](https://help.github.com/articles/adding-a-new-ssh-key-to-your-github-account/)
    2. [Setup Acquia Cloud SSH Keys](https://docs.acquia.com/acquia-cloud/ssh/generate)
6. Clone the repository. By default, Git names this "origin" on your local.
    ```
    $ git clone git@github.com:uiowa/uiowa
    ```
----
# Local Environment
[Ddev](https://ddev.readthedocs.io/en/stable/) is used for the local environment. Follow their [docs](https://ddev.readthedocs.io/en/stable/#installation) to get it installed. Once installed, read up on [basic CLI](https://ddev.readthedocs.io/en/stable/users/cli-usage/) usage to understand how to manage the containers.

Once installed and started, you can either `ddev ssh` and run non-ddev CLI commands there, or run them on your host with `ddev CMD`. For example, `ddev blt dsa` or `ddev composer install`.

## Workspaces
Yarn [workspaces](https://classic.yarnpkg.com/en/docs/workspaces) can be defined in the top-level package.json file. Each workspace can depend on other workspaces as well as define their own build script. You can run workspace build scripts on the web container with `ddev yarn workspace WORKSPACE_NAME run SCRIPT_NAME`. Every workspace build script gets run during continuous integration to build assets. The build assets are committed to the build artifact and deployed.

Workspaces that need to leverage uiowa/uids assets should depend on uids_base and not uiowa/uids directly. This is to ensure the version of uiowa/uids is strictly managed and because uids_base runs a build script that copies necessary assets into the build artifact. For example, fonts are available in uids_base which would not be available in the excluded node_modules directory.

## Databases
Ddev creates a database container that is accessible from the web container. You can access the database container [from your host](https://ddev.readthedocs.io/en/stable/users/topics/database_management/) as well using tools like [SequelPro](https://www.sequelpro.com/) or [TablePlus](https://tableplus.com/).

## Logging
As long as a site has a local settings file, it should be configured to show all warnings and errors to the screen. Other log messages can be viewed by running `ddev logs`.

### BLT Configuration
Make sure you have an [Acquia Cloud key and secret](https://docs.acquia.com/acquia-cloud/develop/api/auth/) saved in the `blt/local.blt.yml` file. This file is ignored by Git. Be sure you do not accidentally commit your credentials to the `blt/blt.yml` file which is tracked in Git. Do not share your key or secret with anyone.
```
uiowa:
  credentials:
    acquia:
      key: foo
      secret: bar
```

Set the multisites that you want BLT to sync by default:
```
multisites:
  - default
  - bar.uiowa.edu
  - foo.uiowa.edu
```

### Common Tasks
Multisites will not be able to bootstrap without a `local.settings.php` file. The `blt:init:settings` (or `bis` for short) command will generate local settings files _only_ for the multisite defined in BLT configuration. By default, this is all multisites but be aware that the `local.blt.yml` change documented above will override that. You can temporarily remove that override if you need to generate settings files for all multisites.

The `blt frontend` command will install and compile frontend assets.

Local configuration overrides can be set in the local.settings.php file for each multisite. For example, to configure stage file proxy:
```
$config['stage_file_proxy.settings']['origin'] = 'https://mysite.com';
$config['stage_file_proxy.settings']['hotlink'] = TRUE;
```

## Multisite Management
There are a few custom BLT commands to manage multisites. Run `blt list uiowa` to see all the commands in the `uiowa` namespace. Then run `blt CMD --help` for more information on specific commands.

Because the `.git` directory is not synced to the web container, some commands need to be run on your host machine instead. You can run `./vendor/bin/blt` from the project root or install the [BLT Launcher](https://github.com/acquia/blt-launcher) to just run `blt`.

### Overriding Configuration
Please note this approach is not yet tested nor recommended.

If an individual site wants to export ALL of its configuration and manage it going forward, an [include setting](https://docs.acquia.com/blt/install/next-steps/#adding-settings-to-settings-php) with the following should accomplish that:
```
$blt_override_config_directories = FALSE;
$settings['config_sync_directory'] = DRUPAL_ROOT . '/config/' . $site_dir;
```

# Updating Dependencies
Before starting updates, make sure your local environment is on a feature branch created from the latest version of the default branch and synced with production by running `blt dsa`. After updating, certain scaffold files may need to be resolved/removed. For example, the htaccess patch might need to be regenerated if it does not apply to the new `.htaccess` file. BLT may download default config files that we don't use like `docroot/sites/default/default.services.yml`. Different updates may require difference procedures.

Configuration tracked in the repository will need to be exported before deployment. To ensure configuration is exported correctly, manually sync a site from production using Drush. Then run database updates and export any configuration changes. Add and commit the config changes and then run another `blt dsa` to check for any further config discrepancies. If there are none, proceed with code deployment as per usual.

## Testing Dependencies
Testing a uids change in uiowa:
1. Update the hash with the uids commit you wish you test in the uids_base package.json file: "@uiowa/uids": "uiowa/uids#[Enter hash here]"
2. Then run `yarn upgrade @uiowa/uids`
3. `rm -rf ./node_modules`
4. `yarn cache clean`
5. `yarn install`
6. `yarn workspace uids_base gulp --development`

## Core
Follow the `drupal/core-recommended` [instructions](https://github.com/drupal/core-recommended#upgrading) on updating.

## Contrib
You can run `composer update package/name` to update additional dependencies. The output from the Composer commands can be used as the long text for commit messages. Ideally, each package update would be one commit for clarity and easier reverting.

### Locked Packages
The packages below are locked at specific SHAs and will not update using the method described above. They should be periodically checked for new stable releases and updated, if viable.

| Package                               | Reason                   |
| ------------------------------------- | ------------------------ |
| drupal/cshs                           | No stable release since fe1b07101d724e6aa5fbcd78c50ce2780534ed0f |
| drupal/lb_direct_add                  | No 2.x stable release.   |
| drupal/menu_link_weight               | No stable release since [f4a4b71b](https://git.drupalcode.org/project/menu_link_weight/-/commit/f4a4b71be5850ebc9d15a5cc742eafb76ef9cd0f). |
| drupal/photoswipe                     | Need 71e54fbcca748c7ec2cfc3d2fdd92c9a180b5852. No stable release with patch       |
| drupal/redirect                       | Need e5201ca5 from 8.x-1.x branch plus a patch. https://git.drupalcode.org/project/redirect/-/commits/8.x-1.x       |
| drupal/reroute_email                  | No stable release since 438a67caeb0b0cc47d1deb0cee50afda9a907dc8 |
| kartsims/easysvg                      | Need https://github.com/kartsims/easysvg/pull/27 which is not included in a release. |
| uiowa/block_content_template          | Forked from a deprecated project. |
| dompdf/dompdf                         | https://www.drupal.org/project/entity_print/issues/3169624 |

# Redirects
Redirects can be added to the docroot/.htaccess file. The .htaccess file be will deployed to all applications, regardless of the domain. Therefore, creating per-site redirects using the Redirect module is preferred.

Note that too many .htaccess redirects can incur a performance hit. See the [Acquia redirect documentation](https://docs.acquia.com/acquia-cloud/manage/htaccess/) for more information and examples.

Ideally, redirects in .htaccess would only exist temporarily. Check the commit history of that file using a command similar to: `git log --before="6 months ago" --grep="redirect" -- docroot/.htaccess` to see how old a redirect is.

# Resources
Additional [BLT documentation](https://docs.acquia.com/blt/) may be useful. You may also access a list of BLT commands by running this:
```
$ blt
```

Most of the BLT commands referenced above have shorthand aliases. Check the output of `blt` for details.

You can also run blt commands on an Acquia Cloud environment, but you must run them using the path and from the app root. `./vendor/bin/blt my:blt:command foo`

## Working With a BLT Project

BLT projects are designed to instill software development best practices (including git workflows).

Our BLT Developer documentation includes an [example workflow](https://docs.acquia.com/blt/developer/dev-workflow/#workflow-example-local-development).
