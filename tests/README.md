This directory should contain automated tests, organized into subdirectories according to testing tool.

Please see [BLT documentation](https://docs.acquia.com/blt/developer/testing/) for more information.

You can use this project to run core and contrib tests. This can be useful when
working on a merge request for a core or contrib project. Here is some
information in addition to the BLT testing docs above to help get you started.

### Set up a project using a source repository.
By default, Composer installs dependencies without VCS repos for performance
reasons. However, you can reinstall a dependency with a source repo by deleting
it and running `composer install` again with the `--prefer-source` option.
```
rm -rf docroot/modules/contrib/module_name
composer install --prefer-source
cd docroot/modules/contrib/module_name
```
Follow the Git instructions on the drupal.org issue you're working on for
setting up another remote within that project directory.

### Run tests
Drupal tests are run with the `blt tests:drupal` command. Verbose logging with
the `-v`option can be useful. By default, no tests will run because there are
none specified in BLT configuration. You can pick and choose what tests to run
by modifying your `blt/local.blt.yml` file as documented in the BLT docs. Here
is an example you can copy and past into `blt/local.blt.yml` to get started.
```
# Configure Drupal tests to run on ddev.
tests:
  drupal:
    web-driver: false
    sudo-run-tests: false
    mink-driver-args-webdriver: ''
    simpletest-db: ''
    simpletest-base-url: 'https://web'
    phpunit:
      -
        # The directory to scan for tests. Change to what you want to test.
        directory: '${docroot}/modules/contrib/entity_usage'
        # Use the core PHPUnit config file.
        config: '${docroot}/core/phpunit.xml.dist'
        # Filter by a specific test type, class, name, etc.
        filter: EmbeddedContentTest
```

You can add more items to `phpunit` or just change it depending on what you
want to test. The latter is probably faster as core/contrib tests can take a
long time to run.

Note that we are disabling Drupal core tests in `blt/ci.blt.yml`. There is no
reason to run them for every commit in CI. We only want to test our code there.

The webdriver arguments are overridden because those are set automatically by
the chromedriver service. See https://github.com/drud/ddev-contrib/tree/master/docker-compose-services/drupalci-chromedriver
for details.
