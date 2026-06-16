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
  TOTAL_PHP=$(echo "$PHP_FILES" | wc -l | tr -d ' ')
  CURRENT=0

  while IFS= read -r file; do
    if [ -f "$file" ]; then
      CURRENT=$((CURRENT + 1))
      PHP_COUNT=$((PHP_COUNT + 1))

      # Show progress every 50 files or on first/last file
      if [ $((CURRENT % 50)) -eq 0 ] || [ $CURRENT -eq 1 ] || [ $CURRENT -eq $TOTAL_PHP ]; then
        echo -ne "  Processing PHP files: $CURRENT / $TOTAL_PHP\r"
      fi

      if ! php -l "$file" > /dev/null 2>&1; then
        echo -e "\n${RED}✗ Syntax error in: $file${NC}"
        php -l "$file"
        PHP_LINT_FAILED=1
      fi
    fi
  done <<< "$PHP_FILES"

  echo "" # New line after progress

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
  TOTAL_YAML=$(echo "$YAML_FILES" | wc -l | tr -d ' ')
  CURRENT=0

  while IFS= read -r file; do
    if [ -f "$file" ]; then
      CURRENT=$((CURRENT + 1))
      YAML_COUNT=$((YAML_COUNT + 1))

      # Show progress every 20 files or on first/last file
      if [ $((CURRENT % 20)) -eq 0 ] || [ $CURRENT -eq 1 ] || [ $CURRENT -eq $TOTAL_YAML ]; then
        echo -ne "  Validating YAML files: $CURRENT / $TOTAL_YAML\r"
      fi

      # Use Symfony YAML component to validate
      if ! php -r "require 'vendor/autoload.php'; use Symfony\Component\Yaml\Yaml; try { Yaml::parseFile('$file'); } catch (\Exception \$e) { fwrite(STDERR, \$e->getMessage() . PHP_EOL); exit(1); }" 2>&1; then
        echo -e "\n${RED}✗ YAML error in: $file${NC}"
        YAML_FAILED=1
      fi
    fi
  done <<< "$YAML_FILES"

  echo "" # New line after progress

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
  TOTAL_TWIG=$(echo "$TWIG_FILES" | wc -l | tr -d ' ')
  CURRENT=0

  while IFS= read -r file; do
    if [ -f "$file" ]; then
      CURRENT=$((CURRENT + 1))
      TWIG_COUNT=$((TWIG_COUNT + 1))

      # Show progress every 20 files or on first/last file
      if [ $((CURRENT % 20)) -eq 0 ] || [ $CURRENT -eq 1 ] || [ $CURRENT -eq $TOTAL_TWIG ]; then
        echo -ne "  Validating Twig files: $CURRENT / $TOTAL_TWIG\r"
      fi

      # Basic check for nested delimiters (common Twig syntax error)
      if grep -qE '\{\{[^}]*\{\{|\}\}[^{]*\}\}|\{%[^%]*\{%|\%\}[^{]*\%\}' "$file" 2>/dev/null; then
        echo -e "\n${RED}✗ Possible nested delimiters in: $file${NC}"
        TWIG_FAILED=1
      fi
    fi
  done <<< "$TWIG_FILES"

  echo "" # New line after progress

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
