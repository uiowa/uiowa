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

## Drupal VM (Vagrant)
### Setup project
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
SSH into your localized Drupal VM environment automated with the BLT launch and automation tools. You'll run most other commands from inside Drupal VM.
    ```
    $ vagrant ssh
    ```

### Site initialization
1. Setup a local Drupal site with an empty database.
Use the `uiowa:multisite` (or `uisite` or `uis`) to generate a new site and the related directories and files. Run this command and follow the prompts.
   ```
   $ blt uis
   ```
   This command will also:

      * Generate the necessary Drupal VM configuration.
      * Create a new git branch and push it to Github for review.
      * Optionally run a site installation.
      * Create a database in Acquia Cloud for each environment.
2. Log into your site with drush.
Access the site and do necessary work at https://uiowa.local.site by running the following commands.
    ```
    $ cd docroot
    $ drush uli
    ```

---
### Other Local Development Tasks

1. Pull Files locally.
Use BLT to pull all files down from your Cloud environment.

   ```console
   $ blt drupal:sync:files
   ```

2. Sync the Cloud Database.
If you have an existing database you can use BLT to pull down the database from your Cloud environment.
   ```console
   $ blt sync
   ```

### Acquia Cloud API Credentials
Running the `blt uis` command requires having an Acquia Cloud API key stored in the `blt/local.blt.yml` file. Create this file if it doesn't already exist and add the following section to it:
```yaml
credentials:
  acquia:
    key: [key]
    secret: [secret]
```
The `key` and `secret` values can be created by visiting this page: https://cloud.acquia.com/a/profile/tokens. The `blt/local.blt.yml` file __must not__ be committed to the git repo.

---
# Contributing

1. [Open a new issue](https://github.com/uiowa/uiowa01/issues/new). Take note of the issue number.
2. Make sure master branch is up-to-date.
    ```console
    git pull
    ```
3. Create a new feature branch. Name your feature branch starting with **feature_** and a brief underscore separate summary of the change.
    ```console
    git checkout -b feature_add_new_content_type
    ```
4. Commit changes regularly as you go.
    ```console
    git commit -am "Added date field to content type."
    ```
5. When your feature is ready to be tested, push it to Github. Please note that you are pushing to a branch of the same name as what you created locally.
    ```console
    git push - origin feature_add_new_content_type
    ```
6. Create a Pull Request for your feature branch. When you push it to Github via the command line, you may see a message like this: 
    ```console
    remote: Create a pull request for 'feature_add_new_content_type' on GitHub by visiting:
    remote:      https://github.com/uiowa/uiowa01/pull/new/feature_add_new_content_type
    ```
    Create the pull request by visiting the referenced URL.
7. Once your feature has been reviewed by another developer, the PR can be merged into the master branch via the Github UI.
8. Nice work!
9. If you have recently restarted your computer, you may need to run the following command for `git push` to work from inside of the vagrant:
    ```console
    ssh-add -K ~/.ssh/id_rsa
    ```

---

# Resources 

Additional [BLT documentation](https://docs.acquia.com/blt/) may be useful. You may also access a list of BLT commands by running this:
```
$ blt
``` 

Note the following properties of this project:
* Primary development branch: _master_
* Local environment: _@[machine_name].local_
* Drupal VM site URL: _https://[machine_name].uiowa.local.site_

The `machine_name` for a site will be generated during the site initialization process. A site with the URL `example.uiowa.edu` would have a machine_name of `example` and a site with the URL `sub.example.uiowa.edu` would have the machine_name of `example_sub`.

## Workflow

We use a _feature branch_ workflow model, as described in [these BLT docs](https://docs.acquia.com/blt/developer/dev-workflow/#feature-branch-workflow).

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
