# CI Scripts

Scripts for running continuous integration checks both locally (DDEV) and in Travis CI.

## Quick Start

### Run All CI Checks Locally

```bash
# Recommended: Fast checks (unit/kernel tests, linting, static analysis)
ddev ci test --filter='Unit|Kernel'  # Unit and kernel tests only
ddev ci lint                         # PHPCS code standards
ddev ci static-analysis              # PHPStan

# Or run unit/kernel tests + linting in one command
ddev ci --skip-setup && ddev ci test --filter='Unit|Kernel'

# Full pipeline (includes functional tests - requires more setup)
ddev ci
```

### Why Skip Functional Tests Locally?

- **Unit tests**: ✅ Fast (~0.03s), no setup needed
- **Kernel tests**: ✅ Fast (~0.1s), minimal setup
- **Functional tests**: ⚠️ Slow (~2-5s), need full Drupal install + web server
- **JavaScript tests**: ⚠️ Very slow (~5-10s), need ChromeDriver

**Best practice:** Run unit/kernel tests locally, let Travis CI run functional tests.

See `FUNCTIONAL_TESTS.md` for details on running functional tests when needed.

## What Gets Checked

1. **Setup** (`setup.sh`)
   - Creates test database
   - Configures environment variables
   - Validates dependencies

2. **Code Standards** (`lint.sh`)
   - Runs PHPCS with `phpcs.xml` configuration
   - Checks against Acquia Drupal Strict standards
   - Validates: custom modules, themes, profiles, BLT commands, tests

3. **Static Analysis** (`static-analysis.sh`)
   - Runs PHPStan for type checking and bug detection
   - Catches potential runtime errors before they happen

4. **Unit Tests** (`test.sh`)
   - Runs PHPUnit with `phpunit.xml.dist` configuration
   - Tests all custom code test suites
   - Can pass extra arguments: `ddev ci test --testsuite=uiowa_core`

## Usage Examples

### Before Committing

```bash
# Run everything (recommended)
ddev ci
```

### When Working on Specific Code

```bash
# Just check coding standards
ddev ci lint

# Fix auto-fixable issues
ddev phpcbf

# Then check again
ddev ci lint
```

### When Writing Tests

```bash
# Run all tests (Unit and Kernel tests work out of the box)
ddev ci test

# Run specific test suite
ddev ci test --testsuite=uiowa_core

# Run only unit tests (fastest, no Drupal installation required)
ddev ci test --testsuite=uiowa_core --filter=Unit

# Run tests from specific directory
ddev ci test docroot/modules/custom/uiowa_core/tests/src/Unit/
```

**Note:** Functional tests require a Drupal installation. See "Running Functional Tests" below.

### Debugging Failed Checks

```bash
# Run with detailed output
ddev ci --fast-fail  # Stop at first failure for quick feedback

# Run individual scripts for more control
ddev exec scripts/ci/setup.sh
ddev exec scripts/ci/lint.sh
ddev exec scripts/ci/test.sh --verbose
```

## Environment Variables

The scripts set these PHPUnit environment variables automatically:

```bash
SIMPLETEST_DB                 # Database connection for tests
SIMPLETEST_BASE_URL          # Base URL for functional tests
BROWSERTEST_OUTPUT_DIRECTORY # Where browser test output goes
SYMFONY_DEPRECATIONS_HELPER  # Disable deprecation warnings
```

In DDEV, these default to:
```bash
SIMPLETEST_DB=mysql://db:db@db/drupal_test
SIMPLETEST_BASE_URL=http://localhost:8080
```

## Travis CI Integration

These same scripts run in Travis CI. The `.travis.yml` file calls:

```yaml
script:
  - scripts/ci/setup.sh
  - scripts/ci/lint.sh
  - scripts/ci/static-analysis.sh
  - scripts/ci/test.sh
```

This ensures **local checks match CI exactly**.

## File Structure

```
scripts/ci/
├── README.md              # This file
├── setup.sh              # Environment setup
├── lint.sh               # PHPCS code standards
├── static-analysis.sh    # PHPStan static analysis
├── test.sh               # PHPUnit tests
└── run-all.sh            # Master script (runs all checks)
```

## Test Types and Requirements

### Unit Tests ✅ (Work out of the box)
- No database required
- No Drupal installation required
- Fastest to run
- Example: `ddev ci test --filter=Unit`

### Kernel Tests ✅ (Work out of the box)
- Require test database (created by setup.sh)
- No full Drupal installation required
- Run with minimal Drupal bootstrap
- Example: `ddev ci test --filter=Kernel`

### Functional Tests ⚠️ (Require setup)
- Require full Drupal installation
- Require working web server
- Slower to run
- See "Running Functional Tests" below

## Running Functional Tests

Functional tests require a Drupal installation. You have two options:

### Option 1: Use Existing Site (Recommended for local dev)

Run tests against your existing DDEV site:

```bash
# Make sure your site is installed
ddev drush @sandbox.local status

# Run tests without reinstalling
INSTALL_DRUPAL=false ddev ci test --filter=Functional
```

### Option 2: Fresh Installation (For CI)

Install a clean Drupal instance just for testing:

```bash
# Install Drupal and run tests
INSTALL_DRUPAL=true ddev ci test
```

**Warning:** Option 2 will wipe your test database and install a fresh Drupal.

## Troubleshooting

### Database Connection Errors

If tests fail with database connection errors:

```bash
# Verify database is running
ddev describe

# Manually create test database
ddev mysql -e "CREATE DATABASE IF NOT EXISTS drupal_test"

# Re-run setup
ddev exec scripts/ci/setup.sh
```

### PHPCS Cache Issues

If PHPCS gives inconsistent results:

```bash
# Clear PHPCS cache
rm -f .phpcs-cache

# Run again
ddev ci lint
```

### Memory Issues

If PHPUnit runs out of memory:

```bash
# Check memory limit
ddev exec php -i | grep memory_limit

# Increase in phpunit.xml.dist if needed (currently set to -1)
```

## Benefits of This Approach

✅ **Catch issues locally** before pushing to CI
✅ **Faster feedback** than waiting for Travis
✅ **Same environment** - DDEV and Travis both use PHP 8.3, MySQL 8.0
✅ **Easy to use** - single `ddev ci` command
✅ **Modular** - run individual checks as needed
✅ **BLT-free** - no dependency on deprecated tooling

## Migration from BLT

### Old BLT Commands

```bash
blt validate    # PHPCS linting
blt tests       # PHPUnit tests
```

### New CI Scripts

```bash
ddev ci lint    # Replaces: blt validate
ddev ci test    # Replaces: blt tests (PHPUnit only)
```

**Note:** These scripts focus on **testing only**. Other BLT functionality (multisite management, deployment) is handled separately and not part of this migration.

## See Also

- Issue: https://github.com/uiowa/uiowa/issues/9852
- PHPUnit config: `phpunit.xml.dist`
- PHPCS config: `phpcs.xml`
- Travis config: `.travis.yml`
