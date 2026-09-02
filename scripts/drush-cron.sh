#!/bin/bash

# Runs drush cron on every site on the current application.
# Triggered by an Acquia scheduled job.
# The key in the manifest is AH_SITE_GROUP (e.g. uiowa07),
# the code path uses AH_SITE_NAME (e.g. uiowa07prod).

log="/shared/logs/drush-cron-${AH_SITE_GROUP}.log"
manifest="/var/www/html/${AH_SITE_NAME}/sitenow/manifest.yml"

sites=$(sed -n "/^${AH_SITE_GROUP}:/,/^[^ ]/p" "$manifest" | grep "  -" | awk '{print $2}')

for site in $sites; do
  echo "----- ${site} -----" &>> "$log"
  drush @${AH_SITE_GROUP}.${AH_SITE_ENVIRONMENT} -l "$site" cron -v 2>&1 | awk '{print "["strftime("%Y-%m-%d %H:%M:%S %Z")"] "$0}' &>> "$log"
done