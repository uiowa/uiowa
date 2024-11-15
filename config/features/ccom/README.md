# Functionality
* Enables the `ccom_core` module which is used on all CCOM websites.
* Unlike other feature splits, this is a module enable first setup:

# Setup

Modules enabled through config split do not trigger the install hooks.

In order to ensure full expected functionality, enable the ccom module and it will enable the split. Then do a regular config import to get these updates.

```
drush en ccom_core
drush cim
```
