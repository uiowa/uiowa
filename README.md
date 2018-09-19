# uiowa
The uiowa application on Acquia Cloud.

## Requirements
- Git
- Passphrase-less SSH key
    - Run `ssh-keygen -p` on the appropriate key to remove a passphrase.
    - If you have forgotten your passphrase you will need to regenerate a new SSH key.
- Lando
    - [Install](https://docs.devwithlando.io/installation/installing.html)
- Credentials
    - https://github.com/uiowa/multisite#requirements

## Usage
1) Clone the application repository to your machine: `git clone git@github.com:uiowa/uiowa.git ~/acquia/uiowa`.
2) Verify that Docker is running.
3) Use a terminal window and navigate to your application directory. For example: `cd ~/acquia/uiowa`.
4) Run `lando start`. This will spin up the docker containers specified in the .lando.yml file.
 
Check the [Lando documentation](https://docs.devwithlando.io/cli/usage.html) for details on using the CLI.

### Composer
This application uses Composer to manage dependencies, including Drupal. Lando will call `composer install` after the
appserver container starts. To get updated dependencies, run `git pull` and then `lando restart`.

### Drush
All Drush commands should be executed on the appserver container. For example:

SSH into the appserver container first:
```
lando ssh
cd docroot/sites/mysite.uiowa.edu
drush status
```

Or execute it via Lando tooling:
```
lando drush status -l mysite.uiowa.edu
```

### Node
All Node commands should be executed on the node container. For example:

SSH into the node container first:
```
lando ssh node
cd docroot/sites/mysite.uiowa.edu/themes/mytheme
npm install
gulp
```

Or execute it via Lando tooling:
```
lando npm install docroot/sites/mysite.uiowa.edu/themes/mytheme
lando gulp --cwd docroot/sites/mysite.uiowa.edu/themes/mytheme
```

#### Multisite
Multisite management is handled via our suite of Drush commands: [multisite](https://github.com/uiowa/multisite). 

After the appserver container starts, lando automatically calls the `drush multisite:generate` command. To sync your
local multisites with the [Drupal manifest](https://github.com/uiowa/drupal-manifest), run `lando restart`.

Lando does not manage multisite databases. However, each multisite settings.php file is configured to connect to a 
specific database. Creating a database can be done by running `drush sql:create` from within the appropriate 
multisite directory. For example:

- `lando ssh`
- `cd docroot/sites/mysite.uiowa.edu`
- `drush sql:create`
- `drush si {profile_name} --sites-subdir mysite.uiowa.edu`
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
debugging. If you need to debug Drush commands, use the vendored path to the Drush wrapper script, e.g. `./vendor/bin/drush my-command`.
