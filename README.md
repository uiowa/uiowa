# uiowa

<<<<<<< HEAD
The base application on Acquia Cloud.
=======
The base application on Acquia Cloud for the University of Iowa.
>>>>>>> master

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
    $ git clone git@github.com:<account>/git@git.com/uiowa/uiowa.git
    ```
7. Add all Pipelines-connected Acquia repositories as another remote. You can get the Acquia remote URL from the [Acquia Cloud interface](https://docs.acquia.com/acquia-cloud/develop/repository/git).
    ```
    git remote add NAME ACQUIA_REMOTE_URL
    ```
----
# Local Environment
### BLT Configuration
Make sure you have an [Acquia Cloud key and secret](https://docs.acquia.com/acquia-cloud/develop/api/auth/) saved in the `blt/local.blt.yml` file. This file is ignored by Git. Be sure you do not accidentally commit your credentials to the `blt/blt.yml` file which is tracked in Git. Do not share your key or secret with anyone.
```
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
The `drupal:sync:all-sites` command will generate settings files only if they
do not exist. If you want to re-generate all multisite local settings files,
you can run `rm -f docroot/sites/*/settings/local.settings.php` beforehand.

The `blt frontend` command will install and compile frontend assets.

Local configuration overrides can be set in the local.settings.php file for
each multisite. For example, to configure stage file proxy:
```
$config['stage_file_proxy.settings']['origin'] = 'https://mysite.com';
$config['stage_file_proxy.settings']['hotlink'] = TRUE;
```

## SiteNow
Please see the [SiteNow README](docroot/profiles/custom/sitenow/README.md) for
addtional local development instructions.

## Provisioning/Deprovisioning
### SiteNow
Please see the [SiteNow README](docroot/profiles/custom/sitenow/README.md) for
provisioning/deprovisioning instructions.

## Databases
Use [SequelPro](https://www.sequelpro.com/) as a GUI for your local databases.

## Updating Dependencies
Before starting updates, make sure your local environment is on a feature branch
created from the latest version of master and synced with production by running
`blt dsa`. Also make sure your are running the same version of PHP as in
production.

Drupal core requires the following specific command to update dev dependencies
properly: `composer update drupal/core webflo/drupal-core-require-dev --with-dependencies`.
You can run `composer update package/name` after that to update additional
dependencies. The output from the Composer commands can be used as the long text
for commit messages. Ideally, each package update would be one commit to the
composer.lock file.

Certain scaffold files should be resolved/removed afterwards. The redirects in
the `docroot/.htaccess` file need to be re-implemented and the `docroot/robots.txt`
should be removed. Different updates may require difference procedures. For
example, BLT may download default config files that we don't use like `docroot/sites/default/default.services.yml`.

Configuration tracked in the repository will need to be exported before deployment.
This varies by the profile used.

### SiteNow
Please see the [SiteNow README](docroot/profiles/custom/sitenow/README.md) for
additional dependency update instructions.

Add and commit the config changes and then run another `blt dsa` to check for
any further config discrepancies. If there are none, proceed with code
deployment as per usual.

# Resources

Additional [BLT documentation](https://docs.acquia.com/blt/) may be useful. You may also access a list of BLT commands by running this:
```
$ blt
```

Most of the BLT commands referenced above have shorthand aliases. Check the
output of `blt` for details.

You can also run blt commands on a remote, but you must run them using the path and from the app root. `./vendor/bin/blt my:blt:command foo`

## Working With a BLT Project

BLT projects are designed to instill software development best practices (including git workflows).

Our BLT Developer documentation includes an [example workflow](https://docs.acquia.com/blt/developer/dev-workflow/#workflow-example-local-development).

## Resources

* GitHub - https://github.com/uiowa/uiowa
<<<<<<< HEAD
* Acquia Cloud
  * uiowa
    * https://cloud.acquia.com/app/develop/applications/6bcc006f-9a0e-425e-aba0-198585dd2b56
  * uiowa01
    * https://cloud.acquia.com/app/develop/applications/21a2a0ab-b4ed-4ecf-8bd4-9266c70f5ef1
=======
* Acquia Cloud subscription - https://cloud.acquia.com/app/develop/applications/6bcc006f-9a0e-425e-aba0-198585dd2b56
>>>>>>> master
