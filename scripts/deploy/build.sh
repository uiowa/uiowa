#!/usr/bin/env bash

##
# Build a deployable artifact from the working tree.
#
# Assembles a clean copy of the repository, then installs production
# dependencies and compiles front-end assets inside it. The result in
# $BUILD_DIR is a runnable codebase with vendor and contrib committed-ready;
# scripts/deploy/distribute pushes it to the Acquia remotes.
#
# Provider-independent: this only runs yarn, composer, and rsync, so it works
# the same locally and in CI. Runs against the working tree (not a clean
# checkout) so an emergency local build can ship uncommitted changes with
# --ignore-dirty.
#
# Usage: scripts/deploy/build.sh [--build-dir=PATH] [--ignore-dirty]
##

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

BUILD_DIR="${REPO_ROOT}/deploy"
IGNORE_DIRTY=0

for arg in "$@"; do
  case "${arg}" in
    --build-dir=*) BUILD_DIR="${arg#*=}" ;;
    --ignore-dirty) IGNORE_DIRTY=1 ;;
    *) echo "build: unknown argument '${arg}'" >&2; exit 1 ;;
  esac
done

# Clean-tree guard. A clean checkout (CI) sails through; locally it blocks an
# accidental build over uncommitted work unless --ignore-dirty is passed for a
# deliberate hotfix-from-local.
if [[ "${IGNORE_DIRTY}" -eq 0 && -n "$(git -C "${REPO_ROOT}" status --porcelain)" ]]; then
  echo "build: working tree is dirty. Commit, stash, or pass --ignore-dirty." >&2
  exit 1
fi

# Assemble the clean tree.
"${SCRIPT_DIR}/assemble.sh" "${BUILD_DIR}"

# Compile front-end assets. yarn classic resolves workspaces from the root
# package.json, so run from the build root, not docroot.
echo "build: installing front-end dependencies and compiling assets"
(
  cd "${BUILD_DIR}"
  yarn install --production --ignore-optional --frozen-lockfile --non-interactive
  yarn workspaces run build
)

# Install production PHP dependencies. This places Drupal core and contrib via
# the composer scaffold/installer plugins, so the artifact carries them.
echo "build: installing production composer dependencies"
composer install \
  --no-dev \
  --optimize-autoloader \
  --no-interaction \
  --working-dir="${BUILD_DIR}"

# Stamp the Git version into custom .info.yml files (logic step: discovers the
# files and edits YAML, so it lives in sn rather than shell).
echo "build: stamping version into custom .info.yml files"
"${REPO_ROOT}/sn" deploy:version-stamp --build-dir="${BUILD_DIR}"

# Settings-layer steps deferred to #9858 (BLT settings replacement):
#   - hash-salt generation (salt.txt)
#   - deployment-identifier generation
# The deploy pipeline no longer does settings work.

echo "build: artifact ready at ${BUILD_DIR}"
