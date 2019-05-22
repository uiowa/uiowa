# UIowa01

An application for deploying sites on the uiowa01 container.

# Getting Started

This project is based on BLT, an open-source project template and tool that enables building, testing, and deploying Drupal installations following Acquia Professional Services best practices. While this is one of many methodologies, it is our recommended methodology. 

1. Review the [Required / Recommended Skills](http://blt.readthedocs.io/en/latest/readme/skills) for working with a BLT project.
2. Ensure that your computer meets the minimum installation requirements (and then install the required applications). See the [System Requirements](http://blt.readthedocs.io/en/latest/INSTALL/#system-requirements).
3. Request access to organization that owns the project repo in GitHub (if needed).
4. Fork the project repository in GitHub.
5. Request access to the Acquia Cloud Environment for your project (if needed).
6. Setup a SSH key that can be used for GitHub and the Acquia Cloud (you CAN use the same key).
    1. [Setup GitHub SSH Keys](https://help.github.com/articles/adding-a-new-ssh-key-to-your-github-account/)
    2. [Setup Acquia Cloud SSH Keys](https://docs.acquia.com/acquia-cloud/ssh/generate)
7. Clone your forked repository. By default, Git names this "origin" on your local.
    ```
    $ git clone git@github.com:<account>/uiowa01.git
    ```
8. To ensure that upstream changes to the parent repository may be tracked, add the upstream locally as well.
    ```
    $ git remote add upstream git@github.com:uiowa/uiowa01.git
    ```

9. Update your the configuration located in the `/blt/blt.yml` file to match your site's needs. See [configuration files](#important-configuration-files) for other important configuration files.


----
# Setup Local Environment.

BLT provides an automation layer for testing, building, and launching Drupal 8 applications. For ease when updating codebase it is recommended to use  Drupal VM. If you prefer, you can use another tool such as Docker, [DDEV](https://blt.readthedocs.io/en/latest/alternative-environment-tips/ddev.md), [Docksal](https://blt.readthedocs.io/en/latest/alternative-environment-tips/docksal.md), [Lando](https://blt.readthedocs.io/en/latest/alternative-environment-tips/lando.md), (other) Vagrant, or your own custom LAMP stack, however support is very limited for these solutions.
## Lando
1. Run `lando start`.
2. Ensure every multisite database exists either through an SQL client or
   executing `lando drush sql:create` for each multisite.
3. Set up the application:
   1. For first-time setup, run `lando blt setup`.
   2. For existing setups, run `lando blt drupal:sync:all-sites`.
      1. Note: this will discard active configuration!
4. Edit `sites.local.php` to route requests to multisite directories:
   ```
   $sites['mysite.uiowa.lndo.site'] = 'mysite';
   ```
5. Profit.

## Runserver
1. Install Homebrew.
2. Install PHP 7.2 via Homebrew.
   ```
   brew install php@7.2
   ```
3. Install MariaDB
   ```
   brew install mariadb
   ```
4. Start MariaDB.
   ```
   brew services start mariadb
   ```
5. Install Composer dependencies.
    ```
    $ composer install
    ```
6. Route the site of your choice in `sites.local.php`.
   ```
   $sites['8888.localhost'] = 'mysite';
   ```
7. Configure `local.settings.php` to override Lando defaults.
8. Start the built-in PHP server.
    ```
    $ drush -l mysite rs --dns
    ```

## Drupal VM (Vagrant)
1. Install Composer dependencies.
After you have forked, cloned the project and setup your blt.yml file install Composer Dependencies. (Warning: this can take some time based on internet speeds.)
    ```
    $ composer install
    ```
2. Setup VM.
Setup the VM with the configuration from this repositories [configuration files](#important-configuration-files).

    ```
    $ vagrant up
    ```

3. Setup a local blt alias.
If the blt alias is not available use this command outside and inside vagrant (one time only).
    ```
    $ composer run-script blt-alias
    ```

4. SSH into your VM.
SSH into your localized Drupal VM environment automated with the BLT launch and automation tools.
    ```
    $ vagrant ssh
    ```

5. Setup a local Drupal site with an empty database.
Use BLT to setup the site with configuration.  If it is a multisite you can identify a specific site.
   ```
   $ blt setup --site=[sitename]
   ```

6. Log into your site with drush.
Access the site and do necessary work at https://uiowa.local.site by running the following commands.
    ```
    $ cd docroot
    $ drush uli
    ```

---
## Other Local Setup Steps

1. Set up frontend build and theme.
By default BLT sets up a site with the lightning profile and a cog base theme. You can choose your own profile before setup in the blt.yml file. If you do choose to use cog, see [Cog's documentation](https://github.com/acquia-pso/cog/blob/8.x-1.x/STARTERKIT/README.md#create-cog-sub-theme) for installation.
See [BLT's Frontend docs](https://blt.readthedocs.io/en/latest/frontend/) to see how to automate the theme requirements and frontend tests.
After the initial theme setup you can configure `blt/blt.yml` to install and configure your frontend dependencies with `blt setup`.

2. Pull Files locally.
Use BLT to pull all files down from your Cloud environment.

   ```
   $ blt drupal:sync:files
   ```

3. Sync the Cloud Database.
If you have an existing database you can use BLT to pull down the database from your Cloud environment.
   ```
   $ blt sync
   ```


---

# Resources 

Additional [BLT documentation](http://blt.readthedocs.io) may be useful. You may also access a list of BLT commands by running this:
```
$ blt
``` 

Note the following properties of this project:
* Primary development branch: master
* Local environment: @<site_folder>.local
* Drupal VM site URL: https://<site_folder>.uiowa.local.site
* Lando site URL: https://<site_folder>.uiowa.lndo.site

## Working With a BLT Project

BLT projects are designed to instill software development best practices (including git workflows). 

Our BLT Developer documentation includes an [example workflow](http://blt.readthedocs.io/en/latest/readme/dev-workflow/#workflow-example-local-development).

### Important Configuration Files

BLT uses a number of configuration (`.yml` or `.json`) files to define and customize behaviors. Some examples of these are:

* `blt/blt.yml` (formerly blt/project.yml prior to BLT 9.x)
* `blt/local.blt.yml` (local only specific blt configuration)
* `box/config.yml` (if using Drupal VM)
* `drush/sites` (contains Drush aliases for this project)
* `composer.json` (includes required components, including Drupal Modules, for this project)

## Resources

* GitHub - https://github.com/uiowa/uiowa01
* Acquia Cloud subscription - https://cloud.acquia.com/app/develop/applications/21a2a0ab-b4ed-4ecf-8bd4-9266c70f5ef1
