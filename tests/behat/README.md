# Behat
In order to run behat tests locally, you must override the DrupalVM SSL certificate in the box/local.config.yml file. It needs to be set to the path where you downloaded the certs. You can use the `box/certs/` directory because it is excluded from VCS. Do not commit these certs to the repo! The local certificates are stored in the developer Wiki.

Example snippet to include in the box/local.config.yml file:
```
apache_vhosts_ssl:
  -
    servername: '{{ drupal_domain }}'
    documentroot: '{{ drupal_core_path }}'
    certificate_file: '/var/www/uiowa/box/certs/local.crt'
    certificate_key_file: '/var/www/uiowa/box/certs/local.key'
    certificate_chain_file: '/var/www/uiowa/box/certs/local-chain.crt'
    extra_parameters: '{{ apache_vhost_php_fpm_parameters }}'

```

Once set, run `vagrant provision` to install the new certificates.

To run behat tests, run `blt behat`. Additional options and filtering can be specified - see `blt behat --help` for details or `blt list tests` for all tests-related commands.

**Note** that behat tests will run against the default site so any created content, users, etc. will be reflected there.
