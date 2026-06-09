#!/usr/bin/env bash

###
# CI Test Script
# Runs PHPUnit tests
###

set -e  # Exit on error
set -u  # Exit on undefined variable

# Color output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}=== Running PHPUnit Tests ===${NC}\n"

# Ensure we're in the project root
cd "${TRAVIS_BUILD_DIR:-/var/www/html}"

# Check if phpunit exists
if [ ! -f "vendor/bin/phpunit" ]; then
  echo -e "${RED}Error: vendor/bin/phpunit not found. Run 'composer install' first.${NC}"
  exit 1
fi

# Ensure database is configured
if [ -z "${SIMPLETEST_DB:-}" ]; then
  echo -e "${YELLOW}Warning: SIMPLETEST_DB not set. Run scripts/ci/setup.sh first.${NC}"

  # Set defaults based on environment
  if [ "${CI:-false}" = "true" ]; then
    # CI environment (Travis, GitHub Actions, etc.)
    export SIMPLETEST_DB="mysql://drupal:drupal@localhost/drupal_test"
    export SIMPLETEST_BASE_URL="${SIMPLETEST_BASE_URL:-http://localhost:8080}"
  else
    # Local environment (DDEV)
    export SIMPLETEST_DB="mysql://db:db@db/drupal_test"
    export SIMPLETEST_BASE_URL="${SIMPLETEST_BASE_URL:-https://uiowa.ddev.site}"
  fi

  export BROWSERTEST_OUTPUT_DIRECTORY="${BROWSERTEST_OUTPUT_DIRECTORY:-/tmp/browsertest_output}"
  export SYMFONY_DEPRECATIONS_HELPER="${SYMFONY_DEPRECATIONS_HELPER:-disabled}"

  # Create browsertest output directory
  mkdir -p "$BROWSERTEST_OUTPUT_DIRECTORY" 2>/dev/null || true

  echo "Using default: $SIMPLETEST_DB"
fi

# Run PHPUnit with verbose output
echo "Configuration: phpunit.xml.dist"
echo "Database: $SIMPLETEST_DB"
echo ""

# Parse arguments for special flags
EXCLUDE_FUNCTIONAL=false
PHPUNIT_ARGS=""

for arg in "$@"; do
  case $arg in
    --exclude-functional)
      EXCLUDE_FUNCTIONAL=true
      ;;
    *)
      PHPUNIT_ARGS="$PHPUNIT_ARGS $arg"
      ;;
  esac
done

# Add exclusion for functional tests if requested
if [ "$EXCLUDE_FUNCTIONAL" = true ]; then
  echo -e "${YELLOW}Excluding Functional and FunctionalJavascript tests${NC}"
  PHPUNIT_ARGS="$PHPUNIT_ARGS --exclude-group functional"
fi

if vendor/bin/phpunit --verbose $PHPUNIT_ARGS; then
  echo -e "\n${GREEN}✓ PHPUnit tests passed${NC}"
  exit 0
else
  echo -e "\n${RED}✗ PHPUnit tests failed${NC}"
  exit 1
fi
