#!/bin/bash
#
# Cloud Hook: post-code-deploy
#
# Runs the SiteNow fleet update after an environment is switched to a different
# release (a branch or tag), e.g. the production release activation. A push to
# the branch an environment already tracks fires post-code-update instead.
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
