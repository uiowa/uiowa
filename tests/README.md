This directory should contain automated tests, organized into subdirectories according to testing tool.

You can use this project to run core and contrib tests. This can be useful when
working on a merge request for a core or contrib project.

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
Drupal tests are run directly with `vendor/bin/phpunit`, using the root
`phpunit.xml.dist` configuration. Point it at whatever you want to test with
the standard PHPUnit arguments: a path, `--filter`, or `--group`. For example,
to run one test class in a contrib module:
```
ddev phpunit --filter LayoutBuilderDirectAddDropbuttonTest docroot/modules/contrib/lb_direct_add
```

`ddev ci test` runs `scripts/ci/test.sh`, the same script CI uses, which wraps
`vendor/bin/phpunit` and accepts `--exclude-functional` to skip Functional and
FunctionalJavascript tests.

The webdriver arguments are set automatically by the chromedriver service. See
https://github.com/drud/ddev-contrib/tree/master/docker-compose-services/drupalci-chromedriver
for details.
