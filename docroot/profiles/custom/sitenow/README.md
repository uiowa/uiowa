# sitenow
The installation profile for ITS-managed Drupal sites. Sites provisioned with
the sitenow profile will be configured to share their sync configuration
directory with the profile.

## Install via Drush
`drush site:install sitenow --existing-config`

## Provision
To create a new multisite:
1. Run the `blt sitenow:multisite:create` command (`smc` for short) on a feature branch created from master.
2. Follow the directions the command prints to the terminal.
3. If necessary, email Hostmaster with CNAME request.

## Deprovision
To delete a multisite:
1. Run the `blt sitenow:multisite:delete` command (`smd` for short) on a feature branch created from master.
2. Follow the directions the command prints to the terminal.
3. Remove any site-specific cron jobs from the Acquia Cloud interface.
3. If necessary, email Hostmaster to remove the CNAME that is no longer in use.
