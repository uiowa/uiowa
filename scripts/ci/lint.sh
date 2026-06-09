#!/usr/bin/env bash

###
# CI Linting Script
# Runs PHP_CodeSniffer to check coding standards
###

set -e  # Exit on error
set -u  # Exit on undefined variable

# Color output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}=== Running PHP_CodeSniffer ===${NC}\n"

# Ensure we're in the project root
cd "${TRAVIS_BUILD_DIR:-/var/www/html}"

# Check if phpcs exists
if [ ! -f "vendor/bin/phpcs" ]; then
  echo -e "${RED}Error: vendor/bin/phpcs not found. Run 'composer install' first.${NC}"
  exit 1
fi

# Run PHPCS with the project's phpcs.xml configuration
echo "Checking code against Acquia Drupal Strict standards..."
echo "Configuration: phpcs.xml"
echo ""

if vendor/bin/phpcs --standard=phpcs.xml; then
  echo -e "\n${GREEN}✓ PHPCS passed - no coding standard violations found${NC}"
  exit 0
else
  echo -e "\n${RED}✗ PHPCS failed - coding standard violations found${NC}"
  echo -e "${YELLOW}Tip: Run 'ddev phpcbf' to automatically fix many issues${NC}"
  exit 1
fi
