# Running Functional Tests

Functional and FunctionalJavascript tests are more complex than Unit and Kernel tests. This document explains the challenges and solutions.

## Why Functional Tests Are Complex

**Functional tests require:**
1. A running web server
2. A fully installed Drupal site
3. Database with content/configuration
4. Browser environment (for JavaScript tests)
5. Correct URLs and paths configured

**Unit and Kernel tests** (simpler):
- Don't need a web server
- Don't need a full Drupal installation
- Work with minimal database setup
- Run much faster

## Current Issues

### Issue 1: phpunit.xml.dist Location

Functional tests spawn subprocesses that run from `docroot/`. They can't find `phpunit.xml.dist` in the project root.

**Solution Applied:**
- Created symlink: `docroot/phpunit.xml -> ../phpunit.xml.dist`
- Setup script creates this automatically
- Added to `.gitignore`

### Issue 2: Site Installation

Functional tests expect a working Drupal site to test against.

**Options:**

1. **Use existing DDEV site** (easiest):
   ```bash
   # Your sandbox.local site is already running
   ddev ci test --filter=Functional
   ```

2. **Fresh install** (for CI):
   ```bash
   INSTALL_DRUPAL=true ddev ci test
   ```

### Issue 3: JavaScript Tests Need Chrome

FunctionalJavascript tests require ChromeDriver for browser automation.

**In DDEV:**
ChromeDriver isn't installed by default. You would need to:
1. Install ChromeDriver in DDEV
2. Configure MINK_DRIVER_ARGS in phpunit.xml.dist
3. Start ChromeDriver service

**In Travis:**
ChromeDriver is available via the `chrome: stable` addon.

## Recommended Approach

### For Local Development

**Focus on Unit and Kernel tests:**
```bash
# Fast, reliable, no web server needed
ddev ci test --filter=Unit
ddev ci test --filter=Kernel
```

**Run functional tests manually when needed:**
```bash
# Against your existing DDEV site
ddev phpunit --filter=Functional --testsuite=uiowa_core
```

### For CI (Travis)

Run all tests including functional:
```yaml
script:
  - scripts/ci/setup.sh
  - scripts/ci/test.sh
```

Travis has the full environment set up (web server, ChromeDriver, etc.)

## Workarounds for Local Functional Tests

If you need to run functional tests locally regularly:

### Option 1: Use Existing Site

```bash
# Make sure site is installed and working
ddev drush @sandbox.local status

# Set correct base URL
export SIMPLETEST_BASE_URL="https://uiowa.ddev.site"
export SIMPLETEST_DB="mysql://db:db@db/db"

# Run functional tests
ddev phpunit --filter=Functional
```

### Option 2: Dedicated Test Site

Install a separate site just for testing:

```bash
# Install to test database
ddev drush site:install sitenow \
  --db-url=mysql://db:db@db/drupal_test \
  --yes

# Run tests against test installation
export SIMPLETEST_DB="mysql://db:db@db/drupal_test"
ddev phpunit --filter=Functional
```

### Option 3: Skip Functional Tests Locally

```bash
# Only run unit and kernel tests
ddev ci test --filter='Unit|Kernel'

# Let Travis CI handle functional tests
git push  # Travis runs all tests
```

## Test Execution Time Comparison

Based on typical execution:

| Test Type | Time | Setup Required |
|-----------|------|----------------|
| Unit | ~0.03s per test | None |
| Kernel | ~0.1s per test | Test database |
| Functional | ~2-5s per test | Full Drupal install + web server |
| FunctionalJavascript | ~5-10s per test | Full setup + ChromeDriver |

**Recommendation:** Run Unit and Kernel tests frequently locally, let CI run functional tests.

## Configuration Reference

### Environment Variables

```bash
# Required for all tests
SIMPLETEST_DB="mysql://db:db@db/drupal_test"

# Required for functional tests
SIMPLETEST_BASE_URL="https://uiowa.ddev.site"
BROWSERTEST_OUTPUT_DIRECTORY="/tmp/browsertest_output"

# Required for JavaScript tests
MINK_DRIVER_CLASS="Drupal\FunctionalJavascriptTests\DrupalSelenium2Driver"
MINK_DRIVER_ARGS='["chrome", {"chromeOptions": {"w3c": false}}, "http://localhost:9515"]'
```

### Files Required

```
phpunit.xml.dist          # Main config (project root)
docroot/phpunit.xml       # Symlink (for functional tests)
```

## Debugging Functional Test Failures

### Check Environment
```bash
# Verify base URL is accessible
curl -I https://uiowa.ddev.site

# Verify database is accessible
ddev mysql -e "SELECT 1"

# Check symlink exists
ls -la docroot/phpunit.xml
```

### Check Browser Output
```bash
# Functional tests save HTML output here:
ls /tmp/browsertest_output/

# View last test output
cat /tmp/browsertest_output/*.html
```

### Run Single Test with Verbose Output
```bash
ddev phpunit \
  --filter=testSpecificTest \
  --verbose \
  --debug
```

## Summary

**For Issue #9852 (Non-BLT Testing):**

✅ **Working well:**
- Unit tests - Fast and reliable
- Kernel tests - Good for testing with minimal Drupal
- PHPCS linting - Works perfectly
- PHPStan static analysis - Works perfectly

⚠️ **Requires setup:**
- Functional tests - Need full Drupal installation
- FunctionalJavascript tests - Need ChromeDriver

**Recommendation:**
- Use `ddev ci test --filter='Unit|Kernel'` for local development
- Let Travis CI run functional tests in the full environment
- Manually run functional tests when working on them specifically

This gives you fast feedback locally while ensuring full test coverage in CI.
