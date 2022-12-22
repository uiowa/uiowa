# Functionality

- Functionality to lock down access to the site to users with the viewer role and above.
- Local database search has been added to override our default Google search for private searching.
- Unlike other feature splits, this is a module enable first setup:

```
drush en sitenow_intranet
drush cim
```

- **Note**: default_content that was installed with the site should be manually removed because they point to the public files directory.
