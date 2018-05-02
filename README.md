# uiowa801
The uiowa application on Acquia Cloud.

## Requirements
- Git
- Passphrase-less SSH key
    - Run `ssh-keygen -p` on the appropriate key to remove a passphrase.
- Lando
    - [Install](https://docs.devwithlando.io/installation/installing.html)

## Lando
[Lando](https://docs.devwithlando.io/) is a free, open source, cross-platform, local development environment and DevOps tool built on [Docker](https://www.docker.com/) container technology.

### Installation
#### Mac OSX
1) [Download](https://github.com/lando/lando/releases) the latest release from Github. (Mac users should download the *.dmg version).
2) Extract and run the installer.
#### Windows
1) Check you meet the requirements: [Lando for Windows Installation](https://docs.devwithlando.io/installation/installing.html#windows).
2) Install [Hyper-V](https://docs.microsoft.com/en-us/virtualization/hyper-v-on-windows/quick-start/enable-hyper-v).
3) [Download](https://github.com/lando/lando/releases) the latest release from Github. (PC users should download the *.exe version).
4) Run the installer.
5) Be sure your user has been added to `docker-users` and `Hyper-V` permission groups.
6) Be sure to add the Lando executable to your system or user PATH environment variable.
   - [Windows Environment Variables](https://www.computerhope.com/issues/ch000549.htm).
   - Add an entry for `C:\Program Files\Lando\bin;`.

### Start the uiowa801 container.
1) Be sure you have cloned the application repository to your machine: `git clone git@github.com:uiowa/uiowa801.git`.
2) Verify that Docker is running.
3) Use a terminal window and navigate to your application directory. For example: `cd ~/aquia/{user}/uiowa801`.
4) Run: `lando start`. This will spin up a new docker container using our custom drupal8 recipe.
5) The Lando instance acts like a remote server. You may ssh into the Lando container by running: `lando ssh` from within the application directory.
 
 Check the [Lando documentation](https://docs.devwithlando.io/cli/usage.html) for more command line commands.

### Known issues
- **Git clone access denied.** It is recommended that you use a phassphrase-less SSH key. You may remove a passphrase on your current keys with: `ssh-keygen -p`. If you have forgotten your passpharse you will need to regenerate new SSH keys and add them where applicable.

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
7)PHPStorm should detect an incoming connection and pop-up a server configuration. Save the configuration and start debugging. 

## Setup a new multisite
1) Be sure your Lando instance is running:
    ```
    lando list
    ```
2) Navigate to your application directory, For example:
   ```
   cd ~/acquia/{user}/uiowa801
   ```
3) SSH into your Lando container:
   ```
   lando ssh
   ```
4) Run generate multisite directory:
    ```
    drush gen msd
    ```
5) Add a site mapping to sites.php. For example: 
    ```
    $sites['somesite.uiowa.lndo.site'] = 'somesite.uiowa.edu';
    ```
6) Run site install:
    ```
    drush si --sites-subdir={site_directory} --db-su=root --db-url=mysql://drupal8:drupal8@database/{site_database_name} uiowa
    ```
7) Login to your site:
    ```
    drush -l {site_url} uli
    ```
