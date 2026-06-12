#!/usr/bin/env bash

###
# CI Setup Script
# Prepares environment for testing (works in Travis CI and DDEV)
###

set -e  # Exit on error
set -u  # Exit on undefined variable

# Color output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}=== CI Setup ===${NC}"

# Detect environment
if [ "${TRAVIS:-false}" = "true" ]; then
  ENV="travis"
  CI_ENV="travis"
  echo "Running in Travis CI"
elif [ "${GITHUB_ACTIONS:-false}" = "true" ]; then
  ENV="github"
  CI_ENV="github"
  echo "Running in GitHub Actions"
elif [ "${CI:-false}" = "true" ]; then
  ENV="ci"
  CI_ENV="generic"
  echo "Running in CI environment"
else
  ENV="local"
  CI_ENV="local"
  echo "Running in local DDEV environment"
fi

# Ensure we're in the project root
# Works for Travis, GitHub Actions, and local
cd "${TRAVIS_BUILD_DIR:-${GITHUB_WORKSPACE:-/var/www/html}}"

# Check for required binaries
echo -e "\n${YELLOW}Checking dependencies...${NC}"
command -v php >/dev/null 2>&1 || { echo -e "${RED}PHP not found${NC}" >&2; exit 1; }
command -v composer >/dev/null 2>&1 || { echo -e "${RED}Composer not found${NC}" >&2; exit 1; }
command -v mysql >/dev/null 2>&1 || { echo -e "${RED}MySQL client not found${NC}" >&2; exit 1; }

echo "PHP version: $(php --version | head -n1)"
echo "Composer version: $(composer --version --no-ansi)"

# Setup test database
echo -e "\n${YELLOW}Setting up test database...${NC}"

if [ "$ENV" = "local" ]; then
  # Local DDEV database setup
  mysql -h db -u root -proot -e "DROP DATABASE IF EXISTS drupal_test;" 2>/dev/null || true
  mysql -h db -u root -proot -e "CREATE DATABASE drupal_test;"
  DB_URL="mysql://db:db@db/drupal_test"
elif [ "$ENV" = "github" ]; then
  # GitHub Actions database setup (uses root password)
  mysql -h 127.0.0.1 -u root -proot -e "DROP DATABASE IF EXISTS drupal_test;" 2>/dev/null || true
  mysql -h 127.0.0.1 -u root -proot -e "CREATE DATABASE drupal_test;"
  mysql -h 127.0.0.1 -u root -proot -e "CREATE USER IF NOT EXISTS 'drupal'@'%' IDENTIFIED BY 'drupal';"
  mysql -h 127.0.0.1 -u root -proot -e "GRANT ALL ON drupal_test.* TO 'drupal'@'%';"
  mysql -h 127.0.0.1 -u root -proot -e "FLUSH PRIVILEGES;"
  DB_URL="mysql://drupal:drupal@127.0.0.1/drupal_test"
else
  # Travis CI and other CI database setup (no root password)
  mysql -u root -e "DROP DATABASE IF EXISTS drupal_test;" 2>/dev/null || true
  mysql -u root -e "CREATE DATABASE drupal_test;"
  mysql -u root -e "CREATE USER IF NOT EXISTS 'drupal'@'localhost' IDENTIFIED BY 'drupal';"
  mysql -u root -e "GRANT ALL ON drupal_test.* TO 'drupal'@'localhost';"
  mysql -u root -e "FLUSH PRIVILEGES;"
  DB_URL="mysql://drupal:drupal@localhost/drupal_test"
fi

echo "Test database created"

# Export environment variables for PHPUnit
export SIMPLETEST_DB="$DB_URL"

# Set base URL based on environment
if [ "$ENV" = "local" ]; then
  # Local DDEV environment
  export SIMPLETEST_BASE_URL="${SIMPLETEST_BASE_URL:-https://uiowa.ddev.site}"
else
  # CI environments (Travis, GitHub Actions, etc.)
  export SIMPLETEST_BASE_URL="${SIMPLETEST_BASE_URL:-http://localhost:8080}"
fi

export BROWSERTEST_OUTPUT_DIRECTORY="${BROWSERTEST_OUTPUT_DIRECTORY:-/tmp/browsertest_output}"
export SYMFONY_DEPRECATIONS_HELPER="${SYMFONY_DEPRECATIONS_HELPER:-disabled}"

# Create browsertest output directory if it doesn't exist
mkdir -p "$BROWSERTEST_OUTPUT_DIRECTORY"
chmod 777 "$BROWSERTEST_OUTPUT_DIRECTORY" 2>/dev/null || true

# Install Drupal for functional tests (optional)
# Only needed if running Functional or FunctionalJavascript tests
# For unit tests and kernel tests, this step is not required
if [ "${INSTALL_DRUPAL:-false}" = "true" ]; then
  echo -e "\n${YELLOW}Installing Drupal for functional tests...${NC}"
  vendor/bin/drush site:install sitenow --yes --db-url="$DB_URL" --account-name=admin --account-pass=admin
  echo "Drupal installed"
fi

# Create phpunit.xml symlink in docroot if it doesn't exist
# This is needed for functional tests to find the configuration
if [ "$ENV" = "local" ] && [ ! -e "${TRAVIS_BUILD_DIR:-${GITHUB_WORKSPACE:-/var/www/html}}/docroot/phpunit.xml" ]; then
  echo -e "\n${YELLOW}Creating phpunit.xml symlink in docroot...${NC}"
  cd "${TRAVIS_BUILD_DIR:-${GITHUB_WORKSPACE:-/var/www/html}}/docroot"
  ln -sf ../phpunit.xml.dist phpunit.xml
  cd "${TRAVIS_BUILD_DIR:-${GITHUB_WORKSPACE:-/var/www/html}}"
fi

# Start web server for functional tests using Drush (optional)
# Only needed if running Functional or FunctionalJavascript tests
if [ "${RUN_SERVER:-false}" = "true" ]; then
  echo -e "\n${YELLOW}Starting web server for functional tests...${NC}"

  PROJECT_ROOT="${TRAVIS_BUILD_DIR:-${GITHUB_WORKSPACE:-/var/www/html}}"

  # Create tmp directory for logs
  mkdir -p "$PROJECT_ROOT/tmp"

  # Kill any existing runserver processes
  pkill -f "drush runserver" || true
  pkill -f "php -S.*8080" || true

  # Start Drush runserver in background
  echo "Starting Drush runserver at $SIMPLETEST_BASE_URL"
  cd "$PROJECT_ROOT/docroot"
  ../vendor/bin/drush runserver "$SIMPLETEST_BASE_URL" > "$PROJECT_ROOT/tmp/runserver.log" 2>&1 &
  SERVER_PID=$!
  echo "$SERVER_PID" > "$PROJECT_ROOT/tmp/php-server.pid"

  # Wait for server to be ready
  echo "Waiting for server to respond..."
  SERVER_STARTED=false
  for i in {1..30}; do
    if curl -f -s -o /dev/null "$SIMPLETEST_BASE_URL"; then
      echo -e "${GREEN}✓ Web server started at $SIMPLETEST_BASE_URL (PID: $SERVER_PID)${NC}"
      SERVER_STARTED=true
      break
    fi
    echo -n "."
    sleep 1
  done

  if [ "$SERVER_STARTED" = false ]; then
    echo -e "\n${RED}✗ Web server failed to start after 30 seconds${NC}"
    echo "Server log:"
    cat "$PROJECT_ROOT/tmp/runserver.log" 2>/dev/null || echo "Log file not found"
    echo "Checking if process is running:"
    ps aux | grep -E "drush|runserver|$SERVER_PID" | grep -v grep
    exit 1
  fi

  cd "$PROJECT_ROOT"
fi

echo -e "\n${GREEN}✓ Setup complete${NC}"
echo "Environment variables set:"
echo "  SIMPLETEST_DB=$SIMPLETEST_DB"
echo "  SIMPLETEST_BASE_URL=$SIMPLETEST_BASE_URL"
echo "  BROWSERTEST_OUTPUT_DIRECTORY=$BROWSERTEST_OUTPUT_DIRECTORY"
