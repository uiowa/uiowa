# Configuration Splits
There are a few prerequisites that you should read and understand before
working with config splits.

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
Developers can create site split directories if a multisite needs to break away
from the default configuration. The machine name of the split should be `site`
and the directory should be `../config/sites/mysite.uiowa.edu` replacing
`mysite.uiowa.edu` with the multisite URL host.

To register a configuration split for a multisite, create the split locally in
the UI and export to the site split directory.
```
drush config-split:export site
```

Deploy the code changes to each environment per the normal process and import
the configuration from the split manually.
```drush @mysite.dev config:import --source ../config/sites/mysite.uiowa.edu --partial```

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

## Best Practices
### Complete Split vs. Conditional Split ?
You will need to decide whether to add your config items to the Complete Split or Conditional Split sections. Following these practices makes it easier for another developer to see at a glance which configuration is new and which is overriding existing configuration.
* Completely split any configuration that is completely unique and not duplicated in the default configuration or another split. This would include custom content types, custom vocabularies, and custom fields added to existing content types.
* Conditionally split configuration that is overriding existing settings, content types, etc. This would include `user.role.*.yml`, re-ordering of fields in the entity display, or the entity form.

### How to split a custom content type
A custom content type consists of several types of interrelated configuration: `node.type.*.yml`, `field.storage.*.*.yml`, `field.field.*.*.*.yml`, `core.entity_form_display.*.*.yml`, and `core.entity_view_display.*.*.yml` at a minimum. The rules of configuration dependencies mean that if you add some of these items, the others will be inferred from that. After you set up your content type, it is a good idea to run `drush @site.local cst` to see a list of the config items that are new or have changed.
* Add the `node.type.*.yml` to the config split first. After that, run `drush @site.local config-split:export site`. You will notice that many config files get exported that were not added to the split.
* Run `drush @site.local cst` again to see what additional config elements need to be added.
