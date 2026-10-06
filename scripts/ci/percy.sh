#!/usr/bin/env bash

###
# CI Percy Script
# Runs Percy visual regression snapshots against a running site
# (works in GitHub Actions and DDEV)
###

set -e  # Exit on error
set -u  # Exit on undefined variable
set -o pipefail  # Surface percy's exit code through the `tee` below

# Load shared color codes
source "$(dirname "$0")/colors.sh"

echo -e "${GREEN}=== Percy Visual Regression ===${NC}\n"

# Ensure we're in the project root
cd "${TRAVIS_BUILD_DIR:-${GITHUB_WORKSPACE:-/var/www/html}}"

# PERCY_TOKEN authenticates with the Percy project. In GitHub Actions, a
# missing token is a misconfiguration and must fail the build rather than
# report success. Locally, skip gracefully since a dev may not have a
# token handy.
if [ -z "${PERCY_TOKEN:-}" ]; then
  if [ "${GITHUB_ACTIONS:-false}" = "true" ]; then
    echo -e "${RED}PERCY_TOKEN not set. Add it as a repo secret to run visual regression tests.${NC}" >&2
    exit 1
  fi

  echo -e "${YELLOW}PERCY_TOKEN not set; skipping Percy snapshot.${NC}"
  echo "To run locally, export a token from https://percy.io and re-run:"
  echo "  PERCY_TOKEN=<token> ddev ci percy"
  exit 0
fi

# Source environment variables from setup.sh if available
ENV_FILE="./tmp/ci-env.sh"
if [ -f "$ENV_FILE" ]; then
  echo "Loading environment variables from $ENV_FILE"
  source "$ENV_FILE"
fi

# Determine the base URL to snapshot against
if [ -z "${SIMPLETEST_BASE_URL:-}" ]; then
  if [ "${GITHUB_ACTIONS:-false}" = "true" ] || [ "${TRAVIS:-false}" = "true" ] || [ "${CI:-false}" = "true" ]; then
    SIMPLETEST_BASE_URL="http://localhost:8080"
  else
    # Local DDEV environment
    SIMPLETEST_BASE_URL="https://uiowa.ddev.site"
  fi
fi

echo "Snapshotting against: $SIMPLETEST_BASE_URL"

if [ ! -f "node_modules/.bin/percy" ]; then
  echo -e "${RED}Percy CLI not found. Run 'yarn install' first.${NC}" >&2
  exit 1
fi

# Percy's CLI treats per-snapshot errors (e.g. a selector that didn't match)
# as non-fatal and still exits 0 so one broken page doesn't kill the whole
# suite. That means a build where every snapshot failed and no build was
# ever created on Percy's side can still report success here. Capture the
# output so we can catch that case explicitly, in addition to the exit code.
PERCY_LOG="$(mktemp)"
trap 'rm -f "$PERCY_LOG"' EXIT

if npx percy snapshot --base-url "$SIMPLETEST_BASE_URL" snapshots.yml | tee "$PERCY_LOG"; then
  if grep -qi "Build not created" "$PERCY_LOG"; then
    echo -e "\n${RED}✗ Percy snapshot failed: no build was created (all snapshots errored)${NC}"
    exit 1
  fi
  echo -e "\n${GREEN}✓ Percy snapshot complete${NC}"
  exit 0
else
  echo -e "\n${RED}✗ Percy snapshot failed${NC}"
  exit 1
fi
