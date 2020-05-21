# Feature Splits
This directory holds feature splits. A feature split is a group of configuration that comprises some feature of functionality. They can be activated on multiple sites. This is not related to the features module.

## Documentation
See the Acquia BLT docs for more information on enabling and disabling feature splits.
https://docs.acquia.com/blt/developer/config-split/#feature-split for more details.

## Disabling a Feature Split
One thing the documentation does not cover is disabling a feature split. To disable or uninstall a feature split, deactivate it in the Config Split interface and then import configuration again.

- Enable = activate + config import
- Disable = deactivate + config import

You may have to delete any existing entities that the feature split created. The `drush entity:delete` can help with that.

