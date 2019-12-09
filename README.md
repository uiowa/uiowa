# uiowa

The base application on Acquia Cloud hosting the Sitenow platform.

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
7. Add the Acquia repository as a secondary remote. You can get the Acquia remote URL from the [Acquia Cloud interface](https://docs.acquia.com/acquia-cloud/develop/repository/git).
    ```
    git remote add acquia ACQUIA_REMOTE_URL
    ```

----
# Setup Local Environment.
1. Install [Drush Launcher](https://github.com/drush-ops/drush-launcher).
    - Ensure that there are no other Drush versions in your $PATH in `~/.bashrc` or `~.bash_profile`.
2. Install [Homebrew](https://brew.sh/).
3. Install [NVM](https://github.com/nvm-sh/nvm#installation-and-update) and then Yarn, globally.
  ```
  npm install --global yarn
  ```
4. Install PHP 7.2 via Homebrew.
   ```
   brew install php@7.2
   brew link php@7.2
   ```
   Follow the instructions to get PHP7.2 in your $PATH.

   You may also want to increase some key PHP resources. Homebrew should have installed PHP at:
   `/usr/local/etc/php/`

   edit the php.ini file within the version of PHP you are using. Consider increasing the following defaults:

   - memory_limit = 256M
   - max_input_vars = 3000

   Save the file.

5. Install MariaDB.
   ```
   brew install mariadb
   ```
   Keep the username `root` with no password.
6. Start MariaDB.
   ```
   brew services start mariadb
   ```
7. Install Composer dependencies.
    ```
    $ composer install
    ```
8. Sync all multisites.
    ```
    blt drupal:sync:all-sites
    ```
9. Start the built-in PHP server.
    ```
    $ drush -l mysite rs --dns
    ```

Visit the site in your browser by navigating to http://localhost:8888.

The `drush/Commands/PolicyCommands.php` file will overwrite the
`sites.local.php` file to route the correct site when running `drush rs`. It is
possible to serve multiple sites from different runserver commands with two
different ports. You'll need to manually edit the `sites.local.php` file in
that scenario.

Once the server is running and the multisite is routed, you can log in by
changing directory to `docroot/sites/mysite` and running `drush uli`. Note that
this only works when the sites.php/local.sites.php file is routing the multisite.

The `drupal:sync:all-sites` command will generate settings files only if they
do not exist. If you want to re-generate all multisite local settings files,
you can run `rm -f docroot/sites/*/settings/local.settings.php` beforehand.

The `blt frontend` command will setup the theme and compile the theme's assets along with any other subthemes within the uiowa repo. Note `blt drupal:sync:all-sites` should complete this step. Check to make sure.

Local configuration overrides can be set in the local.settings.php file for
each multisite. For example, to configure stage file proxy:
```
$config['stage_file_proxy.settings']['origin'] = 'https://mysite.com';
$config['stage_file_proxy.settings']['hotlink'] = TRUE;
```
## Local BLT Configuration
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

## Create Multisite
To create a new multisite:
1. Run the `blt sitenow:multisite:create` command (`smc` for short) on a feature branch created from master.
2. Follow the directions the command prints to the terminal.
3. If necessary, email Hostmaster with CNAME request template in ITS-web@uiowa.edu -> Drafts -> Email Templates -> SiteNow Templates.

## Delete Multisite
To delete a multisite:
1. Run the `blt sitenow:multisite:delete` command (`smd` for short) on a feature branch created from master.
2. Follow the directions the command prints to the terminal.
3. Remove any related cron jobs from the Acquia Cloud interface.
3. If necessary, email Hostmaster to remove the CNAME that is no longer in use.

## XDebug

`pecl install xdebug`

Take note of where the extension is installed by viewing the output of the above command:
```
Build process completed successfully
Installing '/usr/local/Cellar/php@7.2/7.2.18/pecl/20170718/xdebug.so'
install ok: channel://pecl.php.net/xdebug-2.7.2
Extension xdebug enabled in php.ini
```

You may need to change the path if you get an error when running a simple PHP
command like `php --ini`.

Open your php.ini file (should be located at: /usr/local/etc/php/7.2/php.ini). Confirm there is a line like:
`zend_extension="xdebug.so"`

Ideally this should be at the bottom of the file near the other extensions' config. Ultimately, your php.ini should have the following:

```
[xdebug]
zend_extension="xdebug.so"
xdebug.remote_enable=1
xdebug.remote_log=/tmp/xdebug
```

Make sure you IDE is configured to use the same PHP executable. In PHPStorm this is located under Preferences > PHP > CLI Interpeter. Click the dots to configure the location.

If using PHPStorm make sure your .bash_profile has the line and source it `source ~/.bash_profile`:
`export XDEBUG_CONFIG="idekey=PHPSTORM"`

Restart your server and clear the site's cache.

Place a breakpoint and Start listening for PHP Debug Connections. Visit your site where your breakpoint should trigger and accept the incoming connection.

## Databases
Use [SequelPro](https://www.sequelpro.com/) to manage your local databases. You
can connect via localhost using the credentials set when installing MariaDB.

## Updating Dependencies
Before starting updates, make sure your local environment is on a feature branch
created from the latest version of master and synced with production by running
`blt dsa`. Also make sure your are running the same version of PHP as in
production.

Drupal core requires the following specific command to update dev dependencies
properly: `composer update drupal/core webflo/drupal-core-require-dev --with-dependencies`.
You can run `composer update` after that to update all other dependencies. The
output from Composer commands can be used as the long text for commit messages.

Certain scaffold files should be resolved/removed afterwards. The redirects in
the `docroot/.htaccess` file need to be re-implemented and the `docroot/robots.txt`
should be removed. Different updates may require difference procedures. For
example, BLT may download default config files that we don't use like `docroot/sites/default/default.services.yml`.

To ensure configuration is exported correctly, run database updates and export
using our BLT command:
```
blt sitenow:multisite:execute updb
blt sitenow:multisite:execute config:export
```

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

You can also run blt commands on a remote, but you must run them using the path and from the app root. `./vendor/bin/blt sitenow:multisite:execute cr`

## Working With a BLT Project

BLT projects are designed to instill software development best practices (including git workflows).

Our BLT Developer documentation includes an [example workflow](https://docs.acquia.com/blt/developer/dev-workflow/#workflow-example-local-development).

## Resources

* GitHub - https://github.com/uiowa/uiowa
* Acquia Cloud subscription - https://cloud.acquia.com/app/develop/applications/6bcc006f-9a0e-425e-aba0-198585dd2b56
