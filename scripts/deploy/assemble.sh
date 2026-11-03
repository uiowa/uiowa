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

# The build dir is wiped with rm -rf below, so confine it to inside the repo
# (the default is deploy/). Canonicalize first via the parent, since the dir
# may not exist yet, then refuse the repo root itself or any path outside the
# repo. This stops a stray --build-dir (e.g. a home directory or a pasted
# absolute path) from deleting unintended files.
build_parent="$(cd "$(dirname "${BUILD_DIR}")" 2>/dev/null && pwd)" || {
  echo "assemble: build dir parent does not exist: ${BUILD_DIR}" >&2
  exit 1
}
BUILD_DIR="${build_parent}/$(basename "${BUILD_DIR}")"
case "${BUILD_DIR}" in
  "${REPO_ROOT}")
    echo "assemble: refusing to build into the repository root." >&2
    exit 1
    ;;
  "${REPO_ROOT}"/*)
    ;;
  *)
    echo "assemble: refusing to build outside the repository: ${BUILD_DIR}" >&2
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

# rsync's .gitignore emulation does not honor git's cross-file negations: the
# root .gitignore ignores **/assets/js as a build artifact, and uids_base
# re-includes its committed copy with !assets/js. rsync drops it, so restore the
# assets/js files git actually tracks. (assets/css is ignored the same way, but
# gulp regenerates that; assets/js is committed source with nothing to rebuild
# it.) The pathspec covers any theme that adds the same negation later.
git -C "${REPO_ROOT}" ls-files -- ':(glob)**/assets/js/**' \
  | rsync -a --files-from=- "${REPO_ROOT}/" "${BUILD_DIR}/"

echo "assemble: working tree copied to ${BUILD_DIR}"
