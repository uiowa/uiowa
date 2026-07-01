#!/usr/bin/env bash

##
# Assemble the working tree into a clean build directory.
#
# Copies the working tree into $BUILD_DIR, skipping anything git ignores
# (secrets, local config, and the composer/yarn-managed trees the build
# regenerates) plus the tracked cruft listed in deploy-exclude.txt. Nothing
# destructive runs against the source checkout: the build dir is a separate
# tree, so the later --no-dev composer install and front-end build never touch
# the working copy.
#
# Usage: scripts/deploy/assemble.sh <build-dir>
##

set -euo pipefail

BUILD_DIR="${1:?Usage: assemble.sh <build-dir>}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
EXCLUDE_FILE="${SCRIPT_DIR}/deploy-exclude.txt"

# Refuse to assemble into the repo root or any path inside it other than the
# gitignored deploy/ dir; a stray target could wipe the working tree.
case "${BUILD_DIR}" in
  "${REPO_ROOT}" | "${REPO_ROOT}/")
    echo "assemble: refusing to build into the repository root." >&2
    exit 1
    ;;
esac

echo "assemble: preparing clean build dir at ${BUILD_DIR}"
rm -rf "${BUILD_DIR}"
mkdir -p "${BUILD_DIR}"

# --filter ':- .gitignore' makes rsync honor every .gitignore the way git does
# (per-directory, anchored), so untracked secrets and managed trees are skipped.
# --exclude-from covers the tracked gaps. Trailing slash on the source copies
# its contents, not the dir itself.
rsync -a --delete \
  --filter=':- .gitignore' \
  --exclude-from="${EXCLUDE_FILE}" \
  "${REPO_ROOT}/" "${BUILD_DIR}/"

echo "assemble: working tree copied to ${BUILD_DIR}"
