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

# node_modules is build-time only; the artifact ships the compiled assets, not
# the dependencies that produced them.
echo "build: pruning build-time node_modules from the artifact"
find "${BUILD_DIR}" -type d -name node_modules -prune -exec rm -rf {} +

# Install production PHP dependencies. This places Drupal core and contrib via
# the composer scaffold/installer plugins, so the artifact carries them.
echo "build: installing production composer dependencies"
composer install \
  --no-dev \
  --optimize-autoloader \
  --no-interaction \
  --working-dir="${BUILD_DIR}"

# Strip VCS metadata left by packages Composer installs from source (e.g.
# uiowa_auth, a VCS repo with no dist). A nested .git would be committed as an
# empty gitlink instead of the package's files; a nested .gitignore would make
# the artifact commit skip package files.
echo "build: stripping nested VCS metadata from the artifact"
find "${BUILD_DIR}" -name '.git' -type d -prune -exec rm -rf {} +
find "${BUILD_DIR}" -name '.gitignore' -type f -delete

# Strip documentation from the artifact. Markdown is always docs, so all of it
# goes (this also removes model/tooling docs some packages ship, e.g. AGENTS.md
# and CLAUDE.md) except license texts. Plain-text files are removed by
# documentation name only: some libraries (e.g. HTMLPurifier) read bundled .txt
# files at runtime, so a blanket .txt removal would break them.
echo "build: stripping documentation files from the artifact"
find "${BUILD_DIR}" -type f -iname '*.md' \
  -not -iname 'LICENSE*' -not -iname 'COPYING*' -delete
find "${BUILD_DIR}" -type f \( \
    -iname 'CHANGELOG*.txt' -o -iname 'CHANGES*.txt' -o -iname 'INSTALL*.txt' \
    -o -iname 'AUTHORS*.txt' -o -iname 'MAINTAINERS*.txt' \
    -o -iname 'CONTRIBUTING*.txt' -o -iname 'UPGRADE*.txt' \
    -o -iname 'README*.txt' -o -iname 'HISTORY*.txt' \
    -o -iname 'CREDITS*.txt' -o -iname 'TODO*.txt' \
  \) -delete

# Stamp the Git version into custom .info.yml files (logic step: discovers the
# files and edits YAML, so it lives in sn rather than shell).
echo "build: stamping version into custom .info.yml files"
"${REPO_ROOT}/sn" deploy:version-stamp --build-dir="${BUILD_DIR}"

# Settings-layer steps deferred to #9858 (BLT settings replacement):
#   - hash-salt generation (salt.txt)
#   - deployment-identifier generation
# The deploy pipeline no longer does settings work.

echo "build: artifact ready at ${BUILD_DIR}"
