#!/bin/bash
#
# Cloud Hook: post-db-copy
#
# Runs whenever a database is copied between environments (the Workflow page or
# an ODE rebuild). Reconciles the site whose database was copied against the
# target environment's code: database updates, config import, and deploy hooks.
#
# Usage: post-db-copy site target-env db-name source-env

set -ev

site="$1"
target_env="$2"
db_name="$3"

# Run from the application root, where the sn binary and vendor/ live.
repo_root="/var/www/html/$site.$target_env"
cd "$repo_root"

# Reconcile just the site whose database was copied. The application and
# environment come from the AH_* environment variables; the copied database
# name identifies which site to reconcile.
./sn deploy:post-db-copy "$db_name"

set +v
