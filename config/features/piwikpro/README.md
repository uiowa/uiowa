# Functionality
* Enables the `sitenow_piwikpro` and `piwikpro` module.
* Allows webmasters to configure PiwikPRO settings through the UI.

# Setup

Splits enabled through config split do not trigger the install hooks which will turn off uiowa_core's Google Tag and Campus GTM functionality.

In order to ensure full expected functionality, enable the `sitenow_piwikpro` module and then activate the `piwikpro` split.

```
drush en sitenow_piwikpro
drush config-split:activate piwikpro
```
