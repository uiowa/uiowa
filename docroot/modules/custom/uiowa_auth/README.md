# uiowa_auth
Disables Drupal authentication and implements HawkID SSO.

## Requirements
This module requires [drupal/samlauth](https://www.drupal.org/project/samlauth) version ^3.3 or later and all it's dependencies.

It also requires metadata describing the Service Provider (SP) endpoints to be consumed by the Identity Provider (IdP) in ITS. Drupal applications hosted in the uiowa subscription on Acquia Cloud can make use of an aggregate metadata file already in place. Please contact the ITS HelpDesk to get additional applications and domains added to the metadata.

## Installation
Install with Composer, see http://drupal.org/node/1897420 for further information.

## Configuration
Configure the samlauth module as per the metadata. If using the aggregate metadata provided by ITS, a good starting point is the samlauth.settings configuration in the uiowa application. Note the empty strings set for sp_x509_certificate and sp_private_key. This is to prevent the samlauth module from reverting to the keys residing in a directory.

Certain configuration items are blank to avoid exposing sensitive information. The following configuration items **have** to be set using [configuration overrides](https://www.drupal.org/docs/8/api/configuration-api/configuration-override-system):
```
idp_entity_id
idp_single_sign_on_service
idp_single_log_out_service
sp_entity_id
sp_x509_certificate
sp_private_key
idp_certs
```
If deploying to Acquia Cloud, an easy way to do this is with a [secrets.settings.php](https://docs.acquia.com/resource/secrets/) file. This file is loaded automatically in an [Acquia BLT](https://docs.acquia.com/blt/) based project.

## Contact
Please contact the [ITS Help Desk](https://its.uiowa.edu/contact) for any support related to this module.

## Tests
Tests should be run from the root of a Drupal 9 installation against the
default site.

To run test locally:
```
./vendor/bin/phpunit -c docroot/core/ path/to/uiowa_auth/tests
```
