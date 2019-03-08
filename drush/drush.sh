#!/usr/bin/env bash

# Start the SSH agent and add mounted key before execution.
# See: https://github.com/lando/lando/issues/478
eval `ssh-agent` > /dev/null
ssh-add /user/.ssh/id_rsa 2> /dev/null
drush "$@"
