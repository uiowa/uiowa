#!/usr/bin/env bash

###
# CI Test Script - Unit and Kernel Tests Only
# Runs only Unit and Kernel tests (skips Functional tests)
###

set -e
set -u

# Color output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${GREEN}=== Running Unit and Kernel Tests ===${NC}\n"
echo -e "${YELLOW}Note: Skipping Functional and FunctionalJavascript tests${NC}\n"

# Ensure we're in the project root
cd "${TRAVIS_BUILD_DIR:-${GITHUB_WORKSPACE:-/var/www/html}}"

# Run tests with path filters to only include Unit and Kernel tests
vendor/bin/phpunit \
  --configuration phpunit.xml.dist \
  --verbose \
  --testsuite=uiowa_core \
  --filter='/Unit|Kernel/' \
  "$@"
