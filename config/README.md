# Configuration Splits
There are a few prerequisites that you should read and understand before
working with config splits.

- https://docs.acquia.com/blt/developer/configuration-management/
- https://docs.acquia.com/blt/developer/config-split/
- https://www.drupal.org/project/config_split/issues/2885643#comment-12125863

## Config Ignore
### Weight: 100
This split should be used for configuration that editors, webmasters, etc. can
change in production. Think of it as a config split with database storage. The
high weight means config entities in this split will take precedence on import.

Configuration that is ignored cannot be selectively enabled/disabled in
environment splits. Use Drupal's [configuration override system](https://www.drupal.org/docs/8/api/configuration-api/configuration-override-system) if you need to override configuration per environment.

## Site Split
### Weight: 90
Site split directories are created when a site is provisioned. However, the
Config Split configuration is not created by default. A multisite can create
one and it should be named the same as the multisite URI host. BLT will activate
the split, if it exists.

Site splits are stored in the `../config/` directory relative from
the docroot.

To register a configuration split for a multisite, create the split locally in
the UI and export to the site split directory.
```
drush config-split:export mysitesplit
```

Deploy the code changes to each environment per the normal process and import
the configuration from the split manually.
```drush @mysite.dev config:import --source ../config/www.mysite.uiowa.edu --partial```

## Feature Splits
### Weight: 80 (varies)
A feature split is a group of configuration that comprises some feature of
functionality. They can be activated on multiple sites. This is not related to
the features module.

Feature split weights can vary based on if it needs to take precedence over
other splits.

See config/features/README.md for more information.

## Caveats
**DO NOT** split core.extension for your site. This will only lead to problems.
You can complete-split individual modules that your site needs and Config
Split will enable them.

**DO NOT** share theme-dependent configuration in the sync directory if your
site split has complete-split a custom theme. The shared configuration will be
deleted on export which will break BLT's configuration integrity checks. An
example of this would be a block.
