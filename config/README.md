# Configuration Splits
There are a few prerequisites that you should read and understand before 
working with config splits.

- https://blt.readthedocs.io/en/latest/configuration-management/
- https://blt.readthedocs.io/en/latest/config-split/
- https://www.drupal.org/project/config_split/issues/2885643#comment-12125863

## Config Ignore
### Weight: 100
This split should be used for configuration that editors, webmasters, etc. can
change in production. Think of it as a config split with database storage. The
high weight means config entities in this split will take precedence on import.

## Site Split
### Weight: 90
Site splits are not created by default. A site can create one and it should be
named the same as the multisite machine name (site directory). BLT will activate
the split, if it exists. 

## Caveats
**DO NOT** export the same configuration with different values to multiple 
splits, even config ignore.

**DO NOT** split core.extension for your site. This will only lead to problems.
You can blacklist individual modules that your site needs and Config Split will
enable them.

**DO NOT** share theme-dependent configuration in the sync directory if your
site split has blacklisted a custom theme. The shared configuration will be 
deleted on export which will break BLT's configuration integrity checks. An 
example of this would be a block.
