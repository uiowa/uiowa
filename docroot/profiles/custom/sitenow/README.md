# sitenow
Configuration for ITS Drupal sites.

## Install via Drush
`drush site:install sitenow --existing-config`

## Dependencies
Add new dependencies by running `composer require vendor/package --no-update` in the
path/to/sitenow directory. This will update the composer.json file appropriately and prevent
dependencies from being installed in the current directory, which is undesired.

## Testing Pull Requests
Run `composer require uiowa/sitenow:dev-branch-name` from the root of the application. This will update the composer.json and composer.lock files to require this feature branch. This is undesired in the master branch but can be useful for deploying PRs to develop or stage. Leave the changes uncommitted locally. When done testing, reset the composer.* changes and re-install dependencies:

```
git checkout -f -- composer.*
composer install
```

Foo bar
