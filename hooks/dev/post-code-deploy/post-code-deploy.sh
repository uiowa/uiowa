#!/bin/bash
#
# Cloud Hook: post-code-deploy
#
# Runs the SiteNow fleet update after code is deployed to an environment,
# either via the Workflow page or by a push to the tracked build branch. See
# ../README.md for details.
#
# Usage: post-code-deploy site target-env source-branch deployed-tag repo-url
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
