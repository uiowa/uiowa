# Cog: Acquia D8 Theme

* [Installation](#installation)
  * [Create Cog theme](#create-cog-theme)
  * [Set up Local Development](#set-up-local-development)
* [Theme Overview](#theme-overview)
  * [Folder Structure](#folder-structure)
  * [Sass Structure](#sass-structure)
  * [Gulp](#gulp)
  * [JavaScript](#javascript)
  * [Grid System](#grid-system)
  * [Theme Regions](#theme-regions)
  * [Images](#images)
* [Further Documentation](#further-documentation)
* [Build Notes](#build-notes)


---


## Installation

### Create Cog theme

If you are reading this, it is likely that you have already done this. This theme is a starter theme created from a drush generator provided by the [Cog Tools module](https://github.com/acquia-pso/cog_tools). For reference, there are instructions on [creating a new theme](https://cog-tools.readthedocs.io/en/latest/README/#quickstart) in the online docs. Previously Cog provided a very simple base theme, but this version of Cog defaults to being a sub-theme of the Classy core theme.

#### Generating a Cog theme

Download the cog_tools module.
 
`composer require drupal/cog_tools`
 
Enable the cog_tools module

`drush pm:enable cog_tools`

Create a sub theme with drush.

`drush generate cog`

Drush will provide a series of questions to set options for the generated theme. The only value without a default is the theme name.

Enable your new sub theme. For a theme with the machine name `my_theme`:

`drush theme:enable my_theme`


### Set up local development

Once you have created a custom theme, you need to do a bit of environment set up in order to run the build tools. If you would like to review a more detailed explanation of these steps, read the [full setup readme](https://cog-tools.readthedocs.io/en/latest/setup-full/).

#### Acquia BLT + DrupalVM
[Acquia BLT](http://blt.readthedocs.io/en/latest/) is a project build system. If your project is using BLT + DrupalVM, do not use the provided shell script to install node (it won't work anyway). The BLT provided version of DrupalVM has node provisioned out of the box (currently should be node 8). If you need to adjust this version for your team, you can edit the `box/config.yml` variable `nodejs_version`, and then when the machine is provisioned or reprovisioned it will get the new version. For example, to set the version of node to 9+:

* Set `nodejs_version: "9.x"` in `box/config.yml`
* If box is already built, call `vagrant provision` to install the new node version

You may also want to add or remove global node packages in `nodejs_npm_global_packages`, depending on the needs of your project.


#### Using shell script to install node
This shell script is provided to enable more consistent local environment set up when not using a VM or Docker based solution. It installs [nvm](https://github.com/creationix/nvm) and whatever version of node is provided to it as an argument. It is provided for utility only and there is no guarantee it will work on your local system. It will not work on Windows as nvm does not support Windows. [See 'important notes'](https://github.com/creationix/nvm#important-notes).

Steps: 
* Navigate to `themes/custom/my_theme` folder in your terminal
* Make install script executable `chmod +x install-node.sh `
* Install Node.js with `./install-node.sh 8.9.1` and then point to the proper version with `source ~/.bashrc && nvm use --delete-prefix 8.9.1` 
  * (optional) If you are not using avn then run `nvm use 8.9.1` when closing and reopening your session
  * (optional) If you choose to use avn follow the instructions [here](_readme/setup-full.md#avn)
* Run the command `npm install` within your `themes/custom/mytheme` folder
* Install the [Gulp](http://gulpjs.com/) build tool globally using `npm install -g gulp-cli`.
* To confirm Gulp and other items are instantiated `npm run build`
* You can now compile both your Sass and JS with `gulp watch`

## Theme Overview

Cog is a developer-focused theme starterkit created by Acquia's Professional Service front end team. It is intended as a minimalistic baseline for custom theming, while exposing common tools and workflows. Cog provides a small amount of code to get started, but is still packed with utilities to extend.

* Responsive containers built on CSS Grid, with a Susy grid system fallback for legacy browsers
* Initial SMACSS file architecture
* Common Twig files and theme dependencies
* Base preprocess functions for class definitions
* Modular gulp tasks for compiling and linting
* Living style guide construction via KSS-node
  

### Folder Structure

```
|-- config/  (install config for block positions and settings) 
|-- css/  (generated css) 
|-- gulp-tasks/ (modular gulp task files)
|-- images/  (theme images)
|-- js/  (compiled js)
|-- layouts/  (template files for defined layouts)
|-- patterns/  (Component based styling, SCSS files and component templates)
|-- templates/  (folder for Drupal theme template files)
|-- gulpfile.js  (configured gulp file) 
|-- install-node.sh (bash script to install nvm and node)
|-- logo.svg (placeholder logo svg file)
|-- node_modules/  (modules generated by npm)
|-- package.json  (configured to load dependencies by npm)
|-- README.md (This file)
|-- [theme-name].breakpoints.yml (theme default breakpoints)
|-- [theme-name].info.yml (theme config file)
|-- [theme-name].libraries.yml (starter libraries file to load theme assets)
|-- [theme-name].layouts.yml (configuration for provided layouts)
|-- [theme-name].theme (file to use for preprocess functions)
|-- theme-settings.php (file to use for making theme settings available in the GUI)
```

## Sass Structure

Setup of the Sass files so that they are properly broken out in partials and according to the SMACSS methodologies.

```
patterns/
  |-- _config.scss
  |-- _utilities.scss
  |-- base/
  |-- layout/
  |-- components/
  |-- state/
  |-- style-guide-only/
  |---- homepage.md
  |---- kss-only.scss
  |-- style.scss
```

* **\_config.scss** this file is for common variables
* **\_utilities.scss** this file is for common mixins, extends, or similar. As your theme grows you might want to break these out in separate partials
* **base/** intended as the baseline styling for HTML elements that you extend upon and will include things like resets, global typography, or common form selectors.
* **layouts/**  for structural layout that can apply to both the outer containers like the sidebars or headers, but also on inner structural pieces.
* **components/** these module files are the reusable or component parts of our design.
* **state/** modules will adjust when in a particular state, in regards to targeting how changes happen on contextual alterations for regions or similar  
* **style-guide-only/** contains homepage.md which provides the content for the Overview section of the styleguide, and kss-only.scss which generates a css file for styling needed by a component for display in the style guide, but not loaded into the actual theme  

## Gulp 

The Gulp installation and tasks are setup to work on install, but are still intended to be easily updated based on project needs. The tasks are declared in `gulpfile.js` and broken out within the `gulp-tasks/` subfolder. You can list the available Gulp tasks with `gulp --tasks`. The most common gulp task is `gulp watch` when developing locally, which covers Sass compiling, JS linting, and building dynamic styleguides.  

## JavaScript

An example JS file `theme.js` is added by default in the `js/` folder. This file contains sample code wrapped in the `Drupal.behaviors` code standard. This JS file is added to the theme with the following portion of the code from `[theme-name].libraries.yml`. Cog does not have compression enabled for Gulp since it is relying on Drupal's caching system. 

```
lib:
  js:
    js/theme.js: {}
```

## Grid System

The Cog grid structure was setup with the intent of having a very minimalist starting point, utilizing mostly what classes are available in the Classy base theme. In the `layouts/_layout-main.scss` file is basic CSS Grid layout styling to provide simple sidebars. There are Susy fallbacks for legacy browsers in the same .scss file. The body classes to enable this are defined with a preprocess conditional in `[theme-name].theme` for each scenario. 

## Theme Regions

The regions available are standard with classic sidebar region, along with pre and post content areas. The intent is to allow for containers to go full-width and rely on the grid containers for inner Susy containers.

```
[theme-name].info.yml

regions:
  header: 'Header'
  primary_menu: 'Primary menu'
  secondary_menu: 'Secondary menu'
  page_top: 'Page top'
  page_bottom: 'Page bottom'
  featured: 'Featured'
  breadcrumb: 'Breadcrumb'
  content: 'Content'
  sidebar_first: 'Sidebar first'
  sidebar_second: 'Sidebar second'
  footer: 'Footer'

```


## Images 

The images designated for your custom theme can be placed in the `images/` folder. By default we do not have compression setup with subfolder, but do highly recommend based on need. Image compression and spriting requires vast differences with the amount images and this can be a task-intensive process for Gulp and automated builds. However for most of our builds, we do utilize both image compression and spriting with the standard subfolders with Gulp automation workflow: `images/src/` `images/dist/`

## Further Documentation

Cog also ships with an extensive list of documentation and code samples that which were intentionally left out of the theme. 
We have collected all the examples in an easy reference [available here](https://cog-tools.readthedocs.io/en/latest/).

## Build Notes

At the current time warnings are displayed for graceful-fs and lodash, these are known issues with gulp 3.x and will no longer be an issue once gulp 4.x is available.

[graceful-fs warning](https://github.com/gulpjs/gulp/issues/1571)
[lodash warning](https://github.com/gulpjs/gulp/issues/1485)
