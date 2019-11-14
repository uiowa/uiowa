# Configuration Splits
There are a few prerequisites that you should read and understand before 
working with config splits.

- https://docs.acquia.com/blt/developer/configuration-management/
- https://docs.acquia.com/blt/developer/config-split/
- https://www.drupal.org/project/config_split/issues/2885643#comment-12125863
- https://www.drupal.org/project/config_split_ignore

## Config Ignore
### Weight: 100
This split should be used for configuration that editors, webmasters, etc. can
change in production. Think of it as a config split with database storage. The
high weight means config entities in this split will take precedence on import.

Configuration for a module that lives in an environment split that should be
ignored should be set in the Config Split Ignore settings and not the overall
Config Ignore settings. For example, Google Analytics is blacklisted in the 
prod split and ignored in the Config Split Ignore settings for the prod split.
One caveat to this approach is that there is the potential for data loss if 
syncing databases from production and then back again. For example, the Google
Analytics tracking code would be empty in that scenario.

## Site Split
### Weight: 90
Site split directories are created when a site is provisioned. However, the 
Config Split configuration is not not created by default. A multisite can create
one and it should be named the same as the multisite URI host. BLT will activate
the split, if it exists. 

Site splits are stored in the `../config/` directory relative from
the docroot. 

To register a configuration split for a multisite, create the split locally in
the UI and export to the site split directory.
```drush config-split:export mysitesplit```

Deploy the code changes to each environment per the normal process and import 
the configuration from the split manually.
```drush @mysite.dev config:import --source ../config/www.mysite.uiowa.edu --partial```

## Caveats
**DO NOT** export the same configuration with different values to multiple 
splits.

**DO NOT** split core.extension for your site. This will only lead to problems.
You can blacklist individual modules that your site needs and Config Split will
enable them.

**DO NOT** share theme-dependent configuration in the sync directory if your
site split has blacklisted a custom theme. The shared configuration will be 
deleted on export which will break BLT's configuration integrity checks. An 
example of this would be a block.
