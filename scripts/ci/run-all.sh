#!/usr/bin/env bash

###
# Run All CI Checks
# Simulates the complete Travis CI pipeline locally
###

set -e  # Exit on any error
set -u  # Exit on undefined variable

# Load shared color codes
source "$(dirname "$0")/colors.sh"

echo -e "${BLUE}╔════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   Running Full CI Pipeline Locally         ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════╝${NC}\n"

# Track failures
FAILED_CHECKS=()

# Ensure we're in the project root
cd "${TRAVIS_BUILD_DIR:-${GITHUB_WORKSPACE:-/var/www/html}}"

# Get script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Make scripts executable
chmod +x "$SCRIPT_DIR"/*.sh

# Parse arguments
SKIP_SETUP=false
FAST_FAIL=false

for arg in "$@"; do
  case $arg in
    --skip-setup)
      SKIP_SETUP=true
      shift
      ;;
    --fast-fail)
      FAST_FAIL=true
      shift
      ;;
    *)
      ;;
  esac
done

# Run setup
if [ "$SKIP_SETUP" = false ]; then
  echo -e "${YELLOW}▶ Step 1/4: Setup${NC}\n"
  if "$SCRIPT_DIR/setup.sh"; then
    echo -e "${GREEN}✓ Setup passed${NC}\n"
  else
    echo -e "${RED}✗ Setup failed${NC}\n"
    exit 1
  fi
else
  echo -e "${YELLOW}⊘ Skipping setup (--skip-setup flag)${NC}\n"
fi

# Run linting
echo -e "${YELLOW}▶ Step 2/4: Code Standards (PHPCS)${NC}\n"
if "$SCRIPT_DIR/lint.sh"; then
  echo -e "${GREEN}✓ PHPCS passed${NC}\n"
else
  FAILED_CHECKS+=("PHPCS")
  if [ "$FAST_FAIL" = true ]; then
    echo -e "${RED}✗ Failing fast due to --fast-fail flag${NC}"
    exit 1
  fi
  echo -e "${RED}✗ PHPCS failed (continuing...)${NC}\n"
fi

# Run static analysis
echo -e "${YELLOW}▶ Step 3/4: Static Analysis (PHPStan)${NC}\n"
if "$SCRIPT_DIR/static-analysis.sh"; then
  echo -e "${GREEN}✓ PHPStan passed${NC}\n"
else
  FAILED_CHECKS+=("PHPStan")
  if [ "$FAST_FAIL" = true ]; then
    echo -e "${RED}✗ Failing fast due to --fast-fail flag${NC}"
    exit 1
  fi
  echo -e "${RED}✗ PHPStan failed (continuing...)${NC}\n"
fi

# Run tests
echo -e "${YELLOW}▶ Step 4/4: Unit Tests (PHPUnit)${NC}\n"
if "$SCRIPT_DIR/test.sh"; then
  echo -e "${GREEN}✓ PHPUnit passed${NC}\n"
else
  FAILED_CHECKS+=("PHPUnit")
  if [ "$FAST_FAIL" = true ]; then
    echo -e "${RED}✗ Failing fast due to --fast-fail flag${NC}"
    exit 1
  fi
  echo -e "${RED}✗ PHPUnit failed (continuing...)${NC}\n"
fi

# Summary
echo -e "${BLUE}╔════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   CI Pipeline Summary                      ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════╝${NC}\n"

if [ ${#FAILED_CHECKS[@]} -eq 0 ]; then
  echo -e "${GREEN}✓ All checks passed!${NC}"
  echo -e "${GREEN}  Your code is ready to push to CI.${NC}\n"
  exit 0
else
  echo -e "${RED}✗ ${#FAILED_CHECKS[@]} check(s) failed:${NC}"
  for check in "${FAILED_CHECKS[@]}"; do
    echo -e "${RED}  - $check${NC}"
  done
  echo -e "\n${YELLOW}Fix the issues above before pushing to CI.${NC}\n"
  exit 1
fi
