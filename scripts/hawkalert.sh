#!/bin/bash

# The end timestamp is now + 300 seconds (5 minutes).
end=$(( $(date +%s) + 300 ))

while : ; do
start=$(date +%s)

# This is where you run the drush command, wget,
# curl, etc. that you would like to execute once a
# minute.
log="/shared/logs/drush-cron.log"
echo [HAWK ALERT IMPORT] `date` &>> $log
drush @${AH_SITE_GROUP}.${AH_SITE_ENVIRONMENT} -l emergency.uiowa.edu rave-alerts &>> $log

# Ensure it does not run more frequently than once
# per minute by sleeping if the command takes less
# than a minute to execute.
sleep $(( 60 - $(date +%s) + $start ))

# Exit the loop if the current timestamp exceeds the
# end timestamp.
[ $(date +%s) -lt $end ] || break
done
