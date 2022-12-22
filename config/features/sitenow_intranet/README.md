# Functionality

- Functionality to lock down access to the site to users with the viewer role and above.
- Local database search has been added to override our default Google search for private searching.
- Unlike other feature splits, this is a module enable first setup:

# Setup

```
drush en sitenow_intranet
drush cim
```

## Remove default content

default_content that was installed with the site should be manually removed because they point to the public files directory.

- Delete media entities.
- Delete all node content except for Home.
- In Home's layout builder, reset to defaults.
- Delete Home's revisions.
