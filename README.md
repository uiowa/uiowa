# uiowa

The base application on Acquia Cloud hosting the Sitenow platform.

# Getting Started

This project is based on BLT, an open-source project template and tool that enables building, testing, and deploying Drupal installations following Acquia Professional Services best practices. While this is one of many methodologies, it is our recommended methodology.

1. Review the [Required / Recommended Skills](http://blt.readthedocs.io/en/latest/readme/skills) for working with a BLT project.
2. Ensure that your computer meets the minimum installation requirements (and then install the required applications). See the [System Requirements](http://blt.readthedocs.io/en/latest/INSTALL/#system-requirements).
3. Request access to organization that owns the project repo in GitHub (if needed).
4. Request access to the Acquia Cloud Environment for your project (if needed).
5. Setup a SSH key that can be used for GitHub and the Acquia Cloud (you CAN use the same key).
    1. [Setup GitHub SSH Keys](https://help.github.com/articles/adding-a-new-ssh-key-to-your-github-account/)
    2. [Setup Acquia Cloud SSH Keys](https://docs.acquia.com/acquia-cloud/ssh/generate)
6. Clone the repository. By default, Git names this "origin" on your local.
    ```
    $ git clone git@github.com:<account>/git@git.com/uiowa/uiowa.git
    ```
7. Update your the configuration located in the `/blt/blt.yml` file to match your site's needs. See [configuration files](#important-configuration-files) for other important configuration files.


----
# Setup Local Environment.
1. Install [Drush Launcher](https://github.com/drush-ops/drush-launcher).
  - Ensure that there are no other Drush versions in your $PATH in `~/.bashrc` or `~.bash_profile`.
1. Install [Homebrew](https://brew.sh/).
2. Install PHP 7.2 via Homebrew.
   ```
   brew install php@7.2
   brew link php@7.2
   ```
   Follow the instructions to get PHP7.2 in your $PATH.
3. Install MariaDB.
   ```
   brew install mariadb
   ```
   Follow the instructions for securing your MySQL instance.
4. Start MariaDB.
   ```
   brew services start mariadb
   ```
5. Install Composer dependencies.
    ```
    $ composer install
    ```
6. Create all `sites/*/settings/local.settings.php` files.
    ```
    blt blt:init:settings
    ```
7. Configure each `sites/*/settings/local.settings.php` file to connect to the appropriate MySQL database. You'll need to use the user/password you set during step 3. The host should be `localhost`.
8. If the databases do not exist, use `drush sql:create` to create them. For example:
    ```
    drush -l mysite sql:create
    ```
9. Start the built-in PHP server.
    ```
    $ drush -l mysite rs --dns
    ```
10. Sync all multisites.
    ```
    blt drupal:sync:all-sites
    ```
    
Visit the site in your browser by navigating to http://localhost:8888. You can
log in using `drush -l mysite uli`, although Drush returns the incorrect URI.
Copy the path and append to `http://localhost:8888`.
    
The `drush/Commands/PolicyCommands.php` file will overwrite the 
sites.local.php` file to route the correct site when running `drush rs`. It is
possible to serve multiple sites from different runserver commands with two 
different ports. You'll need to manually edit the `sites.local.php` file in 
that scenario.

---

# Resources

Additional [BLT documentation](http://blt.readthedocs.io) may be useful. You may also access a list of BLT commands by running this:
```
$ blt
```

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

* GitHub - https://github.com/uiowa/uiowa
* Acquia Cloud subscription - https://cloud.acquia.com/app/develop/applications/6bcc006f-9a0e-425e-aba0-198585dd2b56
