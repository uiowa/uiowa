#!/bin/bash
#
# Cloud Hook: post-code-update
#
# Runs the SiteNow fleet update after new code is pushed to the branch an
# environment is running (the dev/test path: a push to {branch}-build updates
# every environment tracking it). The prod path switches to a release tag and
# fires post-code-deploy instead.
#
# Usage: post-code-update site target-env source-branch deployed-tag repo-url
#                         repo-type

set -ev

site="$1"
target_env="$2"

# Run from the application root, where the sn binary and vendor/ live.
repo_root="/var/www/html/$site.$target_env"
cd "$repo_root"

# Fleet update: db updates, config import, and deploy hooks across the
# application's multisites. The application and environment are read from the
# AH_* environment variables by deploy:update.
./sn deploy:update

set +v
