# SiteNowConfiguration Splits
This README documents some SiteNow-specifics about config splits. There are a
couple prerequisites that you should read and understand before working with
config splits.

- https://blt.readthedocs.io/en/latest/configuration-management/
- https://blt.readthedocs.io/en/latest/config-split/
- https://www.drupal.org/project/config_split/issues/2885643#comment-12125863

In general, avoid exporting the same configuration (with different values)
 to multiple splits.

## Database
### Weight: 10
This split should be used for configuration that editors, webmasters, etc. can
change in production.

## Site Split
### Weight: 90
Site splits are not created by default. A site can create one and it should be
named the same as the multisite machine name (site directory). BLT will activate
the split, if it exists. **DO NOT** split any configuration that also exists
in the database split, unless the database split config is split off and
modified.

**DO NOT** split core.extension for your site. This will only lead to problems.
You can blacklist individual modules that your site needs.

Note that the sitenow profile has enabled the theme blacklist form item. One
caveat to this approach is that theme-dependent configuration entities, for
example blocks, will get removed from the sync directory when exporting. Be
sure to discard that deletion if you intend to share that configuration.
