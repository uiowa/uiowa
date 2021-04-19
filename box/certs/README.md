Use this directory to store SSL certificates to be installed on DrupalVM. Using a directory within the application root ensures it is transferred to the VM. All files within this directory are ignored from Git.

You must specify the certificate locations in the box/local.config.yml file. Below is an example snippet to include in that file:
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

You can download the local certificates in the [developer wiki](https://wiki.uiowa.edu/display/drupaldev/Drupal+Developer+Documentation). Do not commit these certs to the repo!
