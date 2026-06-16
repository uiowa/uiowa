#!/usr/bin/env bash

###
# CI Linting Script
# Runs multiple linting/validation checks:
# - Composer validation
# - PHP syntax linting
# - YAML syntax validation
# - Twig syntax validation (basic)
# - PHP_CodeSniffer (coding standards)
###

set -e  # Exit on error
set -u  # Exit on undefined variable

# Color output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}=== Running Linting & Validation ===${NC}\n"

# Ensure we're in the project root
cd "${TRAVIS_BUILD_DIR:-${GITHUB_WORKSPACE:-/var/www/html}}"

LINT_FAILED=0

# 1. Validate Composer
echo -e "${YELLOW}1. Validating composer.json...${NC}"
if composer validate --no-check-all --no-check-publish; then
  echo -e "${GREEN}✓ Composer validation passed${NC}\n"
else
  echo -e "${RED}✗ Composer validation failed${NC}\n"
  LINT_FAILED=1
fi

# 2. PHP Syntax Linting
echo -e "${YELLOW}2. Linting PHP files for syntax errors...${NC}"
PHP_FILES=$(find docroot/modules/custom docroot/themes/custom docroot/profiles/custom blt/src tests/phpunit -type f \( -name "*.php" -o -name "*.module" -o -name "*.install" -o -name "*.profile" \) 2>/dev/null || true)
PHP_LINT_FAILED=0
PHP_COUNT=0

if [ -n "$PHP_FILES" ]; then
  while IFS= read -r file; do
    if [ -f "$file" ]; then
      PHP_COUNT=$((PHP_COUNT + 1))
      if ! php -l "$file" > /dev/null 2>&1; then
        echo -e "${RED}✗ Syntax error in: $file${NC}"
        php -l "$file"
        PHP_LINT_FAILED=1
      fi
    fi
  done <<< "$PHP_FILES"

  if [ $PHP_LINT_FAILED -eq 0 ]; then
    echo -e "${GREEN}✓ PHP linting passed ($PHP_COUNT files)${NC}\n"
  else
    echo -e "${RED}✗ PHP linting failed${NC}\n"
    LINT_FAILED=1
  fi
fi

# 3. YAML Validation
echo -e "${YELLOW}3. Validating YAML files...${NC}"
YAML_FILES=$(find docroot/modules/custom docroot/themes/custom docroot/profiles/custom config -type f \( -name "*.yml" -o -name "*.yaml" \) 2>/dev/null || true)
YAML_FAILED=0
YAML_COUNT=0

if [ -n "$YAML_FILES" ] && [ -f "vendor/autoload.php" ]; then
  while IFS= read -r file; do
    if [ -f "$file" ]; then
      YAML_COUNT=$((YAML_COUNT + 1))
      # Use Symfony YAML component to validate
      if ! php -r "require 'vendor/autoload.php'; use Symfony\Component\Yaml\Yaml; try { Yaml::parseFile('$file'); } catch (\Exception \$e) { fwrite(STDERR, \$e->getMessage() . PHP_EOL); exit(1); }" 2>&1; then
        echo -e "${RED}✗ YAML error in: $file${NC}"
        YAML_FAILED=1
      fi
    fi
  done <<< "$YAML_FILES"

  if [ $YAML_FAILED -eq 0 ]; then
    echo -e "${GREEN}✓ YAML validation passed ($YAML_COUNT files)${NC}\n"
  else
    echo -e "${RED}✗ YAML validation failed${NC}\n"
    LINT_FAILED=1
  fi
fi

# 4. Twig Validation (basic)
echo -e "${YELLOW}4. Validating Twig templates (basic check)...${NC}"
TWIG_FILES=$(find docroot/modules/custom docroot/themes/custom docroot/profiles/custom -type f -name "*.html.twig" 2>/dev/null || true)
TWIG_FAILED=0
TWIG_COUNT=0

if [ -n "$TWIG_FILES" ]; then
  while IFS= read -r file; do
    if [ -f "$file" ]; then
      TWIG_COUNT=$((TWIG_COUNT + 1))
      # Basic check for nested delimiters (common Twig syntax error)
      if grep -qE '\{\{[^}]*\{\{|\}\}[^{]*\}\}|\{%[^%]*\{%|\%\}[^{]*\%\}' "$file" 2>/dev/null; then
        echo -e "${RED}✗ Possible nested delimiters in: $file${NC}"
        TWIG_FAILED=1
      fi
    fi
  done <<< "$TWIG_FILES"

  if [ $TWIG_FAILED -eq 0 ]; then
    echo -e "${GREEN}✓ Twig validation passed ($TWIG_COUNT files)${NC}\n"
  else
    echo -e "${RED}✗ Twig validation failed${NC}\n"
    LINT_FAILED=1
  fi
fi

# 5. PHP_CodeSniffer (coding standards)
echo -e "${YELLOW}5. Checking coding standards (PHPCS)...${NC}"
if [ ! -f "vendor/bin/phpcs" ]; then
  echo -e "${RED}Error: vendor/bin/phpcs not found. Run 'composer install' first.${NC}"
  exit 1
fi

echo "Configuration: phpcs.xml"
echo ""

if vendor/bin/phpcs --standard=phpcs.xml; then
  echo -e "\n${GREEN}✓ PHPCS passed${NC}\n"
else
  echo -e "\n${RED}✗ PHPCS failed${NC}"
  echo -e "${YELLOW}Tip: Run 'ddev phpcbf' to automatically fix many issues${NC}\n"
  LINT_FAILED=1
fi

# Final result
if [ $LINT_FAILED -eq 0 ]; then
  echo -e "${GREEN}✓ All linting checks passed${NC}"
  exit 0
else
  echo -e "${RED}✗ Some linting checks failed${NC}"
  exit 1
fi
