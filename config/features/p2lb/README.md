# Functionality

- Functionality to convert sitenow_v2 split sites to default config.

# Setup

```
drush config-split:activate p2lb
drush cim
```

## Finishing conversion

Once the website has finished converting all v2 pages over to a published v3 version:

- Create a database backup through Acquia Cloud UI
- `drush sitenow_p2lb:cleanup`
