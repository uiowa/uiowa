#!/bin/sh
#
# Update New Relic whenever there is a new code deployment.

site=$1         # The site name. This is the same as the Acquia Cloud username for the site.
targetenv=$2    # The environment to which code was just deployed.
sourcebranch=$3 # The code branch or tag being deployed.
deployedtag=$4  # The code branch or tag being deployed.
repourl=$5      # The URL of your code repository.
repotype=$6     # The version control system your site is using; "git" or "svn".


#https://docs.newrelic.com/docs/apm/new-relic-apm/maintenance/recording-deployments#post-deployment
curl -X POST "https://api.newrelic.com/v2/applications/$NEWRELIC_APP_ID/deployments.json" \
     -H "X-Api-Key:$NEWRELIC_API_KEY" -i \
     -H "Content-Type: application/json" \
     -d \
"{
  \"deployment\": {
    \"revision\": \"$deployedtag\",
    \"changelog\": \"$deployedtag deployed to $site.$targetenv\",
    \"description\": \"$deployedtag deployed to $site.$targetenv\",
    \"user\": \"noreply@acquia.com\"
  }
}"
