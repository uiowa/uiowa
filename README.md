# uiowa
The uiowa application on Acquia Cloud.

## Requirements
- Git
- Passphrase-less SSH key
    - Run `ssh-keygen -p` on the appropriate key to remove a passphrase.
    - If you have forgotten your passphrase you will need to regenerate a new SSH key.
- Lando
    - [Install](https://docs.devwithlando.io/installation/installing.html)

## Usage
1) Clone the application repository to your machine: `git clone git@github.com:uiowa/uiowa.git ~/acquia/uiowa`.
2) Verify that Docker is running.
3) Use a terminal window and navigate to your application directory. For example: `cd ~/acquia/uiowa`.
4) Run `lando start`. This will spin up new docker containers using our custom recipes.
5) You can ssh into the appserver container by running: `lando ssh` from within the application directory.
 
 Check the [Lando documentation](https://docs.devwithlando.io/cli/usage.html) for details.

### Composer
This application uses Composer to manage dependencies, including Drupal. Lando will call `composer install` after the
appserver container starts. To get updated dependencies, run `git pull` and then `lando restart`.

### Drush
All Drush commands should be executed on the appserver container. For example:

1) Be sure your Lando instance is running:
    ```
    lando list
    ```
2) Navigate to your application directory, For example:
   ```
   cd ~/acquia/uiowa
   ```
3) SSH into the appserver container:
   ```
   lando ssh
   ```
4) Hello world:
    ```
    drush status
    ```

#### Multisites
Multisite management is handled via our suite of Drush commands: [multisite](https://github.com/uiowa/multisite). 

After the appserver container starts, lando automatically calls the `drush multisite:generate` command. To sync your
local multisites with the [Drupal manifest](https://github.com/uiowa/drupal-manifest), run `lando restart`.

Lando does not manage multisite databases. However, each multisite settings.php file is configured to connect to a 
specific database. Creating a database can be done by running `drush sql:create` from within the appropriate 
multisite directory. For example:

- `lando ssh`
- `cd docroot/sites/mysite.uiowa.edu`
- `drush sql:create`
- `drush si uiowa`
- `drush uli`

### Xdebug and PHPStorm
1) Open PHPStorm preferences.
2) Navigate to Languages & Frameworks -> PHP -> Debug
3) Verify the following settings:
   - **External Connections**
     - Ignore external connections through unregistered server configurations: Unchecked
   - **Xdebug**
     - Debug port: 9000
     - Can accept external connections: Checked
4) Set a breakpoint.
5) Start listening for incoming connections.
6) Refresh the browser page.

PHPStorm should detect an incoming connection and pop-up a server configuration. Save the configuration and start
debugging. 
