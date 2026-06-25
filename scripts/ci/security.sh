#!/usr/bin/env bash

###
# CI Security Check Script
# Checks for security vulnerabilities in Composer dependencies
###

set -e  # Exit on error
set -u  # Exit on undefined variable

# Load shared color codes
source "$(dirname "$0")/colors.sh"

echo -e "${GREEN}=== Running Security Checks ===${NC}\n"

# Ensure we're in the project root
cd "${TRAVIS_BUILD_DIR:-${GITHUB_WORKSPACE:-/var/www/html}}"

# Check if composer exists
if [ ! -f "composer.json" ]; then
  echo -e "${RED}Error: composer.json not found${NC}"
  exit 1
fi

echo -e "${YELLOW}Checking for security vulnerabilities in Composer dependencies...${NC}"
echo "Using: composer audit"
echo ""

# Run composer audit
# Note: This checks Composer packages against the PHP Security Advisories Database
# Exit codes: 0 = no vulnerabilities, 1 = vulnerabilities found
if composer audit; then
  echo -e "\n${GREEN}✓ No security vulnerabilities found${NC}"
  exit 0
else
  EXIT_CODE=$?
  echo -e "\n${RED}✗ Security vulnerabilities found${NC}"
  echo -e "${YELLOW}Review the advisories above and update affected packages.${NC}"
  echo -e "${YELLOW}To update Drupal core: composer update \"drupal/core-*\" --with-all-dependencies${NC}"
  exit $EXIT_CODE
fi
