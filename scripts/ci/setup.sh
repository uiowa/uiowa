#!/usr/bin/env bash

###
# CI Setup Script
# Prepares environment for testing (works in Travis CI and DDEV)
###

set -e  # Exit on error
set -u  # Exit on undefined variable

# Load shared color codes
source "$(dirname "$0")/colors.sh"

echo -e "${GREEN}=== CI Setup ===${NC}"

# Detect environment
if [ "${TRAVIS:-false}" = "true" ]; then
  ENV="travis"
  echo "Running in Travis CI"
elif [ "${GITHUB_ACTIONS:-false}" = "true" ]; then
  ENV="github"
  echo "Running in GitHub Actions"
elif [ "${CI:-false}" = "true" ]; then
  ENV="ci"
  echo "Running in CI environment"
else
  ENV="local"
  echo "Running in local DDEV environment (CI mode)"
  # Export CI=true so that Drupal loads ci.settings.php instead of BLT settings
  export CI="true"
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

# Database setup function
# Creates test database and grants permissions based on environment
setup_database() {
  local db_host=$1
  local root_user=$2
  local root_pass=$3
  local app_user=$4
  local app_pass=$5
  local app_host=$6
  local env_name=$7

  echo "Setting up database for $env_name..."

  # Build MySQL command prefix
  local MYSQL_CMD="mysql -h $db_host -u $root_user"
  [ -n "$root_pass" ] && MYSQL_CMD="$MYSQL_CMD -p$root_pass"

  # Drop and create test database
  $MYSQL_CMD -e "DROP DATABASE IF EXISTS drupal_test;" 2>/dev/null || true
  $MYSQL_CMD -e "CREATE DATABASE drupal_test;"

  # Create user and grant privileges if app user differs from root
  if [ "$app_user" != "$root_user" ]; then
    $MYSQL_CMD -e "CREATE USER IF NOT EXISTS '$app_user'@'$app_host' IDENTIFIED BY '$app_pass';"
    $MYSQL_CMD -e "GRANT ALL PRIVILEGES ON *.* TO '$app_user'@'$app_host' WITH GRANT OPTION;"
    $MYSQL_CMD -e "FLUSH PRIVILEGES;"
    echo "✓ Database created: drupal_test"
    echo "✓ User '$app_user' granted all privileges"
  else
    $MYSQL_CMD -e "FLUSH PRIVILEGES;"
    echo "✓ Database created: drupal_test"
  fi
}

# Setup test database
echo -e "\n${YELLOW}Setting up test database...${NC}"

if [ "$ENV" = "local" ]; then
  # DDEV: host=db, root password=root, app user=db
  setup_database "db" "root" "root" "db" "db" "%" "DDEV"
  DB_URL="mysql://db:db@db/drupal_test"
elif [ "$ENV" = "github" ]; then
  # GitHub Actions: host=127.0.0.1, root password=root, app user=drupal
  setup_database "127.0.0.1" "root" "root" "drupal" "drupal" "%" "GitHub Actions"
  DB_URL="mysql://drupal:drupal@127.0.0.1/drupal_test"
else
  # Travis CI: host=localhost, no root password, app user=drupal
  setup_database "localhost" "root" "" "drupal" "drupal" "localhost" "Travis CI"
  DB_URL="mysql://drupal:drupal@localhost/drupal_test"
fi

echo "Test database ready"

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
  echo "Using database URL: $DB_URL"

  # Run drush from docroot directory and ensure db-url is used
  cd "${TRAVIS_BUILD_DIR:-${GITHUB_WORKSPACE:-/var/www/html}}/docroot"

  # Site install with explicit database URL
  ../vendor/bin/drush site:install sitenow \
    --yes \
    --db-url="$DB_URL" \
    --site-name="Test Site" \
    --account-name=admin \
    --account-pass=admin \
    --verbose

  echo "✓ Drupal installed successfully"

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
  echo "Killing any existing web server processes..."
  pkill -f "drush runserver" || true
  pkill -f "php -S.*8080" || true
  sleep 2

  # Extract port from SIMPLETEST_BASE_URL
  SERVER_PORT=$(echo "$SIMPLETEST_BASE_URL" | sed -n 's/.*:\([0-9]*\).*/\1/p')
  if [ -z "$SERVER_PORT" ]; then
    SERVER_PORT=8080
  fi

  # Start Drush runserver in background
  # Use 0.0.0.0:port to listen on all interfaces, --no-browser to avoid opening browser
  echo "Starting Drush runserver on 0.0.0.0:$SERVER_PORT"
  cd "$PROJECT_ROOT/docroot"
  ../vendor/bin/drush runserver "0.0.0.0:$SERVER_PORT" --no-browser > "$PROJECT_ROOT/tmp/runserver.log" 2>&1 &
  SERVER_PID=$!
  echo "$SERVER_PID" > "$PROJECT_ROOT/tmp/php-server.pid"
  echo "Server PID: $SERVER_PID"

  # Wait for server to be ready
  echo "Waiting for server to respond at $SIMPLETEST_BASE_URL..."
  SERVER_STARTED=false
  for i in {1..60}; do
    # Check if process is still running
    if ! kill -0 $SERVER_PID 2>/dev/null; then
      echo -e "\n${RED}✗ Server process died${NC}"
      break
    fi

    # Check if server responds
    if curl -f -s -o /dev/null "$SIMPLETEST_BASE_URL" 2>/dev/null; then
      echo -e "\n${GREEN}✓ Web server started at $SIMPLETEST_BASE_URL (PID: $SERVER_PID)${NC}"
      SERVER_STARTED=true
      break
    fi
    echo -n "."
    sleep 1
  done

  if [ "$SERVER_STARTED" = false ]; then
    echo -e "\n${RED}✗ Web server failed to start after 60 seconds${NC}"
    echo -e "\nServer log (last 50 lines):"
    tail -50 "$PROJECT_ROOT/tmp/runserver.log" 2>/dev/null || echo "Log file not found or empty"
    echo -e "\nChecking if process is running:"
    ps aux | grep -E "$SERVER_PID" | grep -v grep || echo "Process not found"
    echo -e "\nChecking port usage:"
    netstat -tuln 2>/dev/null | grep ":$SERVER_PORT " || lsof -i ":$SERVER_PORT" 2>/dev/null || echo "Port check commands not available"
    exit 1
  fi

  cd "$PROJECT_ROOT"
fi

# Export environment variables to a file for other scripts to source
# This is necessary because in CI, each script runs in a separate shell
ENV_FILE="${TRAVIS_BUILD_DIR:-${GITHUB_WORKSPACE:-/var/www/html}}/tmp/ci-env.sh"
mkdir -p "$(dirname "$ENV_FILE")"
cat > "$ENV_FILE" <<EOF
export SIMPLETEST_DB="$SIMPLETEST_DB"
export SIMPLETEST_BASE_URL="$SIMPLETEST_BASE_URL"
export BROWSERTEST_OUTPUT_DIRECTORY="$BROWSERTEST_OUTPUT_DIRECTORY"
export SYMFONY_DEPRECATIONS_HELPER="$SYMFONY_DEPRECATIONS_HELPER"
EOF

echo -e "\n${GREEN}✓ Setup complete${NC}"
echo "Environment variables exported to: $ENV_FILE"
echo "  SIMPLETEST_DB=$SIMPLETEST_DB"
echo "  SIMPLETEST_BASE_URL=$SIMPLETEST_BASE_URL"
echo "  BROWSERTEST_OUTPUT_DIRECTORY=$BROWSERTEST_OUTPUT_DIRECTORY"
