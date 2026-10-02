#!/usr/bin/env bash

###
# CI Percy Script
# Runs Percy visual regression snapshots against a running site
# (works in GitHub Actions and DDEV)
###

set -e  # Exit on error
set -u  # Exit on undefined variable

# Load shared color codes
source "$(dirname "$0")/colors.sh"

echo -e "${GREEN}=== Percy Visual Regression ===${NC}\n"

# Ensure we're in the project root
cd "${TRAVIS_BUILD_DIR:-${GITHUB_WORKSPACE:-/var/www/html}}"

# PERCY_TOKEN authenticates with the Percy project. Skip gracefully when it's
# not available (e.g. local runs without a token, or PRs from forks) instead
# of failing the whole pipeline.
if [ -z "${PERCY_TOKEN:-}" ]; then
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

if npx percy snapshot --base-url "$SIMPLETEST_BASE_URL" snapshots.yml; then
  echo -e "\n${GREEN}✓ Percy snapshot complete${NC}"
  exit 0
else
  echo -e "\n${RED}✗ Percy snapshot failed${NC}"
  exit 1
fi
