#!/bin/bash
#
# Cloud Hook: post-code-update
#
# Runs the SiteNow fleet update after new code is pushed to the branch an
# environment is running (a push to {branch}-build updates every environment
# tracking it).
#
# Usage: post-code-update site target-env source-branch deployed-tag repo-url
#                         repo-type

set -ev

site="$1"
target_env="$2"
deployed_ref="$4"

# Run from the application root, where the sn binary and vendor/ live.
repo_root="/var/www/html/$site.$target_env"
cd "$repo_root"

# Fleet update: db updates, config import, and deploy hooks across the
# application's multisites. The application and environment are read from the
# AH_* environment variables by deploy:update; the deployed ref is recorded in
# the run log.
./sn deploy:update --ref="$deployed_ref"

set +v
