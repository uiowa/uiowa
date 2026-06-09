#!/usr/bin/env bash

###
# CI Static Analysis Script
# Runs PHPStan for static code analysis
###

set -e  # Exit on error
set -u  # Exit on undefined variable

# Color output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}=== Running PHPStan Static Analysis ===${NC}\n"

# Ensure we're in the project root
cd "${TRAVIS_BUILD_DIR:-/var/www/html}"

# Check if phpstan exists
if [ ! -f "vendor/bin/phpstan" ]; then
  echo -e "${RED}Error: vendor/bin/phpstan not found. Run 'composer install' first.${NC}"
  exit 1
fi

# Check if phpstan.neon config exists
if [ ! -f "phpstan.neon" ]; then
  echo -e "${YELLOW}Warning: phpstan.neon not found${NC}"
fi

# Define paths to analyze (matching BLT's tests:deprecated command)
PATHS=(
  "tests/"
  "docroot/profiles/custom/"
  "docroot/modules/custom/"
  "docroot/themes/custom/"
  "docroot/sites/"
)

echo "Analyzing code for potential bugs and errors..."
echo "Configuration: phpstan.neon"
echo "Paths:"
for path in "${PATHS[@]}"; do
  echo "  - $path"
done
echo ""

# Run PHPStan on each path
# Note: Running on all paths at once can be memory intensive
# We run them separately to provide better error reporting
FAILED_PATHS=()

for path in "${PATHS[@]}"; do
  if [ -d "$path" ]; then
    echo -e "${YELLOW}▶ Analyzing $path${NC}"

    if vendor/bin/phpstan analyse -c phpstan.neon "$path"; then
      echo -e "${GREEN}✓ $path passed${NC}\n"
    else
      echo -e "${RED}✗ $path failed${NC}\n"
      FAILED_PATHS+=("$path")
    fi
  else
    echo -e "${YELLOW}⊘ Skipping $path (does not exist)${NC}\n"
  fi
done

# Report results
if [ ${#FAILED_PATHS[@]} -eq 0 ]; then
  echo -e "${GREEN}✓ PHPStan passed - no errors found${NC}"
  exit 0
else
  echo -e "${RED}✗ PHPStan failed in the following paths:${NC}"
  for path in "${FAILED_PATHS[@]}"; do
    echo -e "${RED}  - $path${NC}"
  done
  exit 1
fi
