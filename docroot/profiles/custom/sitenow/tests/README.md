# Tests
Tests should be run against the default site, not a multisite. See 
ProfileTestBase for why that is. Browser tests require the base URL environment
variable be set and kernel tests require a database connection. These are set
in the .lando.yml configuration file for all SSH connections to the appserver.

## Running Tests
Run the following command from the root of the application:
```
lando phpunit -c docroot/core/ docroot/profiles/sitenow/tests
```

## Debugging Tests
Path mappings don't seem to be auto-detected when running some tests. XDebug
will break initially but clicking 'Resume program' will continue execution.
