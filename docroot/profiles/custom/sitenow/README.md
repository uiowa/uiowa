# sitenow
The installation profile for ITS-managed Drupal sites.

## Install via Drush
`drush site:install sitenow --existing-config`

## Setup
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
8. [Install BLT](https://docs.acquia.com/blt/developer/onboarding/) in case you didn't find it in the getting started section.
    ```
    ./vendor/bin/blt blt:init:shell-alias -y
    ```
9. Sync all multisites. Hopefully you have designated just a few sites to start with in Step 8 or it will probably error out based on the number of sites we now have.
    ```
    blt drupal:sync:all-sites
    ```
10. Start the built-in PHP server.
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


## Provisioning/Deprovisioning
To create a new multisite:
1. Run the `blt sitenow:multisite:create` command (`smc` for short) on a feature branch created from master.
2. Follow the directions the command prints to the terminal.
3. If necessary, email Hostmaster with CNAME request template in ITS-web@uiowa.edu -> Drafts -> Email Templates -> SiteNow Templates.

To delete a multisite:
1. Run the `blt sitenow:multisite:delete` command (`smd` for short) on a feature branch created from master.
2. Follow the directions the command prints to the terminal.
3. Remove any related cron jobs from the Acquia Cloud interface.
3. If necessary, email Hostmaster to remove the CNAME that is no longer in use.

## Configuration
To ensure configuration is exported correctly, run database updates and export
using our BLT command:

```
blt sitenow:multisite:execute updb
blt sitenow:multisite:execute config:export
```
